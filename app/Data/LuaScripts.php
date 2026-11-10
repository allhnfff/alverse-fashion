<?php

declare(strict_types=1);

namespace App\Data;

/**
 * Skrip Lua yang dijalankan Redis secara ATOMIK (EVAL).
 *
 * Kenapa Lua?
 *   Selama satu skrip berjalan, Redis tidak menjalankan perintah klien lain.
 *   Jadi "cek stok -> potong stok -> simpan pesanan -> catat request_id" terjadi
 *   sebagai satu langkah yang tidak bisa disela checkout lain (mencegah race condition).
 *
 * PENTING: Redis TIDAK punya rollback otomatis. Jika satu perintah gagal di tengah skrip,
 * perintah tulis sebelumnya TETAP tersimpan. Karena itu setiap skrip dibagi dua tahap:
 *   TAHAP 1  : hanya membaca + memvalidasi semuanya. Bila ada masalah -> keluar tanpa menulis.
 *   TAHAP 2  : menulis, dengan data yang sudah pasti valid.
 */
final class LuaScripts
{
    /**
     * KEYS[1] checkout:request:{request_id}
     * KEYS[2] order:{order_id}
     * KEYS[3] order:{order_id}:items
     * KEYS[4] order:{order_id}:history
     * KEYS[5] branch:{branch_id}:orders
     * KEYS[6] customer:{session_hash}:orders
     * KEYS[7] inventory:movements
     * KEYS[8..] per item i: inventory:stock:{product_id}, brand:catalog:product:{product_id}
     * ARGV[1] payload_hash  ARGV[2] header JSON  ARGV[3] items JSON
     * ARGV[4] ongkir        ARGV[5] waktu UNIX   ARGV[6] TTL catatan request (detik)
     */
    public const CHECKOUT = <<<'LUA'
local existing = redis.call('GET', KEYS[1])
if existing then
  local rec = cjson.decode(existing)
  if rec['payload_hash'] == ARGV[1] then
    return cjson.encode({ok = true, status = 'duplicate', order_id = rec['order_id']})
  end
  return cjson.encode({ok = false, code = 'IDEMPOTENCY_CONFLICT'})
end
if redis.call('EXISTS', KEYS[2]) == 1 then
  return cjson.encode({ok = false, code = 'ORDER_ID_COLLISION'})
end

local header = cjson.decode(ARGV[2])
local items = cjson.decode(ARGV[3])
local shipping = tonumber(ARGV[4])
local now = tonumber(ARGV[5])
if #items == 0 then
  return cjson.encode({ok = false, code = 'EMPTY_ORDER'})
end
if #KEYS ~= 7 + 2 * #items then
  return cjson.encode({ok = false, code = 'BAD_REQUEST'})
end

-- ===== TAHAP 1: VALIDASI (belum ada penulisan) =====
local problems = {}
local need = {}
local lines = {}
local subtotal = 0
local units = 0
for i, it in ipairs(items) do
  local skey = KEYS[6 + 2 * i]
  local pkey = KEYS[7 + 2 * i]
  local qty = tonumber(it['qty'])
  local variant = it['variant']
  if qty == nil or qty < 1 or qty ~= math.floor(qty) then
    table.insert(problems, {code = 'INVALID_QTY', product_id = it['product_id'], variant = variant})
  else
    local p = redis.call('HMGET', pkey, 'active', 'price', 'name', 'category')
    local price = tonumber(p[2])
    if p[1] ~= '1' or price == nil then
      table.insert(problems, {code = 'PRODUCT_UNAVAILABLE', product_id = it['product_id'], variant = variant})
    else
      local stock = redis.call('HGET', skey, variant)
      if not stock then
        table.insert(problems, {code = 'VARIANT_NOT_FOUND', product_id = it['product_id'], variant = variant})
      else
        local k = skey .. '#' .. variant
        need[k] = (need[k] or 0) + qty
        local available = tonumber(stock)
        if available < need[k] then
          table.insert(problems, {code = 'INSUFFICIENT_STOCK', product_id = it['product_id'], variant = variant,
                                  available = available, requested = need[k]})
        end
        lines[i] = {skey = skey, product_id = it['product_id'], variant = variant, qty = qty,
                    name = p[3], category = p[4], unit_price = price, line_total = price * qty,
                    size_label = it['size_label'] or '', color_label = it['color_label'] or ''}
        subtotal = subtotal + price * qty
        units = units + qty
      end
    end
  end
end
if #problems > 0 then
  return cjson.encode({ok = false, code = problems[1]['code'], problems = problems})
end

-- ===== TAHAP 2: PENULISAN (semua data sudah valid) =====
local oid = header['order_id']
for _, l in ipairs(lines) do
  local after = redis.call('HINCRBY', l.skey, l.variant, -l.qty)
  redis.call('RPUSH', KEYS[3], cjson.encode({
    product_id = l.product_id, name = l.name, category = l.category, variant = l.variant,
    size_label = l.size_label, color_label = l.color_label, qty = l.qty,
    unit_price = l.unit_price, line_total = l.line_total}))
  redis.call('XADD', KEYS[7], 'MAXLEN', '~', '10000', '*',
    'type', 'sale', 'order_id', oid, 'branch_id', header['branch_id'],
    'product_id', l.product_id, 'variant', l.variant,
    'change', tostring(-l.qty), 'after', tostring(after), 'at', tostring(now))
end

header['items_subtotal'] = subtotal
header['shipping_fee'] = shipping
header['total'] = subtotal + shipping
header['total_units'] = units
header['status'] = 'dikonfirmasi'
header['restocked'] = '0'
header['created_at'] = now
header['updated_at'] = now
local flat = {}
for k, v in pairs(header) do
  table.insert(flat, k)
  table.insert(flat, tostring(v))
end
redis.call('HSET', KEYS[2], unpack(flat))
redis.call('RPUSH', KEYS[4], cjson.encode({status = 'dikonfirmasi', at = now, by = 'sistem',
  note = 'Pembayaran simulasi berhasil; stok global dipotong.'}))
redis.call('ZADD', KEYS[5], now, oid)
redis.call('ZADD', KEYS[6], now, oid)
redis.call('SET', KEYS[1], cjson.encode({order_id = oid, payload_hash = ARGV[1], created_at = now}),
  'EX', tonumber(ARGV[6]))
return cjson.encode({ok = true, status = 'created', order_id = oid})
LUA;

    /**
     * KEYS[1] order:{id}  KEYS[2] order:{id}:items  KEYS[3] order:{id}:history  KEYS[4] inventory:movements
     * KEYS[5..] inventory:stock:{product_id} untuk setiap item
     * ARGV[1] branch_id  ARGV[2] alasan  ARGV[3] pelaku  ARGV[4] waktu UNIX
     */
    public const CANCEL = <<<'LUA'
if redis.call('EXISTS', KEYS[1]) == 0 then
  return cjson.encode({ok = false, code = 'ORDER_NOT_FOUND'})
end
local h = redis.call('HMGET', KEYS[1], 'branch_id', 'status', 'restocked')
if h[1] ~= ARGV[1] then
  return cjson.encode({ok = false, code = 'ORDER_NOT_FOUND'})
end
if h[2] == 'dibatalkan' then
  return cjson.encode({ok = true, status = 'already_cancelled'})
end
if h[2] ~= 'dikonfirmasi' then
  return cjson.encode({ok = false, code = 'NOT_CANCELLABLE', current = h[2]})
end
if h[3] == '1' then
  return cjson.encode({ok = false, code = 'STATE_INCONSISTENT'})
end
if ARGV[2] == '' then
  return cjson.encode({ok = false, code = 'REASON_REQUIRED'})
end

-- TAHAP 1: validasi data item + kunci stok yang dideklarasikan
local declared = {}
for i = 5, #KEYS do declared[KEYS[i]] = true end
local raw = redis.call('LRANGE', KEYS[2], 0, -1)
local lines = {}
for i, r in ipairs(raw) do
  local it = cjson.decode(r)
  local q = tonumber(it['qty'])
  if type(it['product_id']) ~= 'string' or type(it['variant']) ~= 'string' or q == nil or q < 1 then
    return cjson.encode({ok = false, code = 'BAD_ORDER_DATA'})
  end
  local skey = 'inventory:stock:' .. it['product_id']
  if not declared[skey] then
    return cjson.encode({ok = false, code = 'KEYS_MISMATCH'})
  end
  lines[i] = {skey = skey, product_id = it['product_id'], variant = it['variant'], qty = q}
end

-- TAHAP 2: kembalikan stok TEPAT SATU KALI + tandai restocked
local now = tonumber(ARGV[4])
for _, l in ipairs(lines) do
  local after = redis.call('HINCRBY', l.skey, l.variant, l.qty)
  redis.call('XADD', KEYS[4], 'MAXLEN', '~', '10000', '*',
    'type', 'cancel_restock', 'order_id', string.sub(KEYS[1], 7), 'branch_id', ARGV[1],
    'product_id', l.product_id, 'variant', l.variant,
    'change', tostring(l.qty), 'after', tostring(after), 'at', tostring(now))
end
redis.call('HSET', KEYS[1], 'status', 'dibatalkan', 'cancel_reason', ARGV[2], 'cancelled_by', ARGV[3],
  'cancelled_at', tostring(now), 'updated_at', tostring(now), 'restocked', '1')
redis.call('RPUSH', KEYS[3], cjson.encode({status = 'dibatalkan', at = now, by = ARGV[3],
  note = 'Dibatalkan: ' .. ARGV[2] .. '. Stok global dikembalikan.'}))
return cjson.encode({ok = true, status = 'cancelled'})
LUA;

    /**
     * KEYS[1] order:{id}  KEYS[2] order:{id}:history  KEYS[3] branch:{branch_id}:completed
     * ARGV[1] branch_id ARGV[2] status_asal ARGV[3] status_tujuan ARGV[4] pelaku ARGV[5] waktu
     * ARGV[6] kurir ARGV[7] resi ARGV[8] kode_pickup ARGV[9] JSON transisi sah
     */
    public const UPDATE_STATUS = <<<'LUA'
if redis.call('EXISTS', KEYS[1]) == 0 then
  return cjson.encode({ok = false, code = 'ORDER_NOT_FOUND'})
end
local h = redis.call('HMGET', KEYS[1], 'branch_id', 'shipping_method', 'status', 'pickup_code')
if h[1] ~= ARGV[1] then
  return cjson.encode({ok = false, code = 'ORDER_NOT_FOUND'})
end
local method, current, to = h[2], h[3], ARGV[3]
if current == to then
  return cjson.encode({ok = true, status = 'unchanged'})
end
if current ~= ARGV[2] then
  return cjson.encode({ok = false, code = 'STATUS_CONFLICT', current = current})
end
local tr = cjson.decode(ARGV[9])
if type(tr[method]) ~= 'table' or tr[method][current] ~= to then
  return cjson.encode({ok = false, code = 'INVALID_TRANSITION', current = current})
end
if to == 'dikirim' and (ARGV[6] == '' or ARGV[7] == '') then
  return cjson.encode({ok = false, code = 'TRACKING_REQUIRED'})
end
if method == 'pickup' and to == 'selesai' and ARGV[8] ~= h[4] then
  return cjson.encode({ok = false, code = 'PICKUP_CODE_MISMATCH'})
end

local now = ARGV[5]
redis.call('HSET', KEYS[1], 'status', to, 'updated_at', now)
local note = 'Status menjadi ' .. to
if to == 'dikirim' then
  redis.call('HSET', KEYS[1], 'courier', ARGV[6], 'tracking_number', ARGV[7], 'shipped_at', now)
  note = 'Dikirim via ' .. ARGV[6] .. ', resi ' .. ARGV[7]
elseif to == 'siap_diambil' then
  redis.call('HSET', KEYS[1], 'ready_at', now)
  note = 'Barang demo dinyatakan siap diambil'
elseif to == 'selesai' then
  redis.call('HSET', KEYS[1], 'completed_at', now)
  redis.call('ZADD', KEYS[3], now, string.sub(KEYS[1], 7))
  if method == 'pickup' then note = 'Diserahkan; kode pickup cocok' else note = 'Pesanan selesai' end
end
redis.call('RPUSH', KEYS[2], cjson.encode({status = to, at = tonumber(now), by = ARGV[4], note = note}))
return cjson.encode({ok = true, status = 'updated'})
LUA;
}
