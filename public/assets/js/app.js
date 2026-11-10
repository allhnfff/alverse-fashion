/* =====================================================================
   Alverse Bogor: JavaScript sisi browser
   PENTING: browser TIDAK pernah terhubung ke Redis. Semua data stok diambil
   dari server PHP lewat /api/stok/{product_id}.
   ===================================================================== */
(function () {
    'use strict';

    // -----------------------------------------------------------------
    // 1. Pembaruan stok bersama di halaman produk
    //    - saat halaman dibuka
    //    - setiap 5 detik selama tab aktif
    //    - saat tab kembali aktif
    //    Angka bisa terlambat beberapa detik; checkout tetap divalidasi server.
    // -----------------------------------------------------------------
    function initStockPolling() {
        var page = document.getElementById('product-page');
        var dataEl = document.getElementById('stock-data');
        if (!page || !dataEl) return;

        var initial;
        try { initial = JSON.parse(dataEl.textContent || '{}'); } catch (e) { return; }

        var state = {
            productId: initial.productId,
            pollMs: initial.pollMs || 5000,
            variants: initial.variants || null,
            fetchedAt: initial.fetchedAt || null,
            error: !!initial.error,
            lastShown: null
        };
        var form = document.getElementById('buy-form');
        var box = document.getElementById('stock-box');
        var valueEl = document.getElementById('stock-value');
        var metaEl = document.getElementById('stock-meta');
        var addBtn = document.getElementById('add-btn');
        var qtyInput = document.getElementById('qty');
        var timer = null;
        var inFlight = false;

        function selected(name) {
            var el = form.querySelector('input[name="' + name + '"]:checked');
            return el ? el.value : '';
        }

        function avail(code) {
            return !!state.variants && Object.prototype.hasOwnProperty.call(state.variants, code) && state.variants[code] > 0;
        }

        // Ukuran yang habis untuk warna terpilih tidak bisa dipilih (wireframe hal. 3).
        // Jika ukuran terpilih ternyata habis, pindah ke ukuran pertama yang masih ada.
        // Tanpa data stok (gagal dimuat), tidak ada yang dikunci; server tetap mengecek saat checkout.
        function lockSoldOutSizes() {
            var known = !!state.variants && !state.error;
            var color = selected('color');
            var inputs = Array.prototype.slice.call(form.querySelectorAll('input[name="size"]'));
            if (known) {
                var cur = form.querySelector('input[name="size"]:checked');
                if (cur && !avail(cur.value + '_' + color)) {
                    var first = inputs.filter(function (i) { return avail(i.value + '_' + color); })[0];
                    if (first) first.checked = true;
                }
            }
            inputs.forEach(function (input) {
                var out = known && !avail(input.value + '_' + color);
                var label = input.closest('.option');
                label.classList.toggle('is-empty', out);
                input.disabled = out && !input.checked; // pilihan yang sedang dicentang tetap terkirim
                var tag = label.querySelector('small');
                if (out && !tag) { tag = document.createElement('small'); tag.textContent = 'habis'; label.querySelector('span').appendChild(tag); }
                if (!out && tag) tag.remove();
            });
            // Warna ditandai habis bila semua ukurannya habis (tetap bisa diklik untuk melihat).
            form.querySelectorAll('input[name="color"]').forEach(function (input) {
                var any = inputs.some(function (s) { return avail(s.value + '_' + input.value); });
                input.closest('.option').classList.toggle('is-empty', known && !any);
            });
        }

        function render() {
            lockSoldOutSizes();
            var code = selected('size') + '_' + selected('color');
            var has = state.variants && Object.prototype.hasOwnProperty.call(state.variants, code);
            var stock = has ? state.variants[code] : null;
            box.classList.remove('is-error', 'is-out');

            if (state.error) {
                // Jangan tampilkan "Stok 0" saat gagal mengambil data.
                box.classList.add('is-error');
                valueEl.textContent = 'Tidak dapat memperbarui stok';
                metaEl.textContent = (state.variants && has)
                    ? 'Angka terakhir yang diketahui: ' + stock + ' (pukul ' + state.fetchedAt + ' WIB). Saat checkout, stok dicek ulang.'
                    : 'Coba lagi beberapa saat. Saat checkout, stok dicek ulang.';
                addBtn.disabled = false;
                form.dispatchEvent(new CustomEvent('stockrender'));
                return;
            }
            if (!has) {
                valueEl.textContent = 'Kombinasi tidak tersedia';
                box.classList.add('is-out');
                addBtn.disabled = true;
            } else if (stock <= 0) {
                valueEl.textContent = 'Habis';
                box.classList.add('is-out');
                addBtn.disabled = true;
            } else {
                valueEl.textContent = String(stock);
                addBtn.disabled = false;
                if (qtyInput) qtyInput.max = String(Math.min(99, stock));
            }
            metaEl.textContent = state.fetchedAt ? 'Diperbarui ' + state.fetchedAt + ' WIB' : '';

            var key = code + ':' + stock;
            if (state.lastShown !== null && state.lastShown !== key && state.lastShown.split(':')[0] === code) {
                box.classList.remove('is-flash');
                void box.offsetWidth; // ulang animasi
                box.classList.add('is-flash');
            }
            state.lastShown = key;
            form.dispatchEvent(new CustomEvent('stockrender'));
        }

        function schedule() {
            clearTimeout(timer);
            timer = setTimeout(function () { refresh(); }, state.pollMs);
        }

        function refresh() {
            if (document.hidden) { return; } // tidak polling saat tab tidak aktif
            if (inFlight) { schedule(); return; }
            inFlight = true;
            var ctrl = ('AbortController' in window) ? new AbortController() : null;
            var abortTimer = ctrl ? setTimeout(function () { ctrl.abort(); }, 4000) : null;
            fetch('/api/stok/' + encodeURIComponent(state.productId), {
                headers: { 'Accept': 'application/json' },
                cache: 'no-store',
                credentials: 'same-origin',
                signal: ctrl ? ctrl.signal : undefined
            }).then(function (res) {
                return res.json().then(function (data) { return { ok: res.ok, data: data }; });
            }).then(function (r) {
                if (!r.ok || !r.data || r.data.ok !== true) throw new Error('stok');
                state.variants = r.data.variants || {};
                state.fetchedAt = r.data.fetched_at_label;
                state.error = false;
            }).catch(function () {
                state.error = true;
            }).then(function () {
                if (abortTimer) clearTimeout(abortTimer);
                inFlight = false;
                render();
                schedule();
            });
        }

        form.addEventListener('change', function (e) {
            if (e.target && (e.target.name === 'size' || e.target.name === 'color')) {
                state.lastShown = null;
                render();
            }
        });
        document.addEventListener('visibilitychange', function () {
            if (!document.hidden) { refresh(); } else { clearTimeout(timer); }
        });
        window.addEventListener('pageshow', function (e) { if (e.persisted) refresh(); });

        render();
        refresh(); // ambil sekali saat halaman dibuka
    }

    // -----------------------------------------------------------------
    // 2. Checkout: tampilkan alamat untuk reguler, info lokasi untuk pickup
    // -----------------------------------------------------------------
    function initCheckoutForm() {
        var form = document.getElementById('checkout-form');
        if (!form) return;
        var addressField = document.getElementById('address-field');
        var address = document.getElementById('address');
        var pickupInfo = document.getElementById('pickup-info');

        function update() {
            var checked = form.querySelector('input[name="shipping_method"]:checked');
            var method = checked ? checked.value : 'reguler';
            document.body.setAttribute('data-method', method);
            var reguler = method === 'reguler';
            addressField.hidden = !reguler;
            address.required = reguler;
            pickupInfo.hidden = reguler;
        }
        form.addEventListener('change', update);
        update();
    }

    // -----------------------------------------------------------------
    // 3. Cegah klik ganda di sisi tampilan (server TETAP idempoten lewat request_id)
    // -----------------------------------------------------------------
    function initSubmitOnce() {
        document.querySelectorAll('form.js-once').forEach(function (form) {
            form.addEventListener('submit', function () {
                var btn = form.querySelector('button[type="submit"]');
                if (btn) {
                    setTimeout(function () {
                        btn.disabled = true;
                        btn.classList.add('is-busy');
                        btn.textContent = 'Memproses…';
                    }, 0);
                }
            });
        });
    }

    // Galeri produk: satu slide per warna, digeser ke samping (scroll-snap di CSS).
    // - Memilih warna menggeser galeri ke gambar warna itu.
    // - Menggeser galeri TIDAK mengubah warna yang dipilih (sama seperti marketplace).
    // - Penanda "1/3" (ponsel) dan thumbnail aktif (layar lebar) mengikuti posisi geser.
    function initGallery() {
        var track = document.getElementById('pd-track');
        if (!track) return;
        var slides = track.querySelectorAll('.pd-slide');
        var count = document.getElementById('pd-count');
        var thumbs = document.querySelectorAll('.pd-thumb');
        var form = document.getElementById('buy-form');
        var reduce = window.matchMedia('(prefers-reduced-motion: reduce)');
        var current = -1;

        function go(i, instant) {
            if (i < 0 || i >= slides.length) return;
            track.scrollTo({ left: i * track.clientWidth, behavior: instant || reduce.matches ? 'auto' : 'smooth' });
        }
        function indexOfColor(code) {
            for (var i = 0; i < slides.length; i++) {
                if ((' ' + slides[i].getAttribute('data-colors') + ' ').indexOf(' ' + code + ' ') !== -1) return i;
            }
            return -1;
        }
        function mark() {
            var i = Math.round(track.scrollLeft / Math.max(1, track.clientWidth));
            if (i === current) return;
            current = i;
            if (count) count.textContent = (i + 1) + '/' + slides.length;
            for (var t = 0; t < thumbs.length; t++) {
                if (t === i) thumbs[t].setAttribute('aria-current', 'true'); else thumbs[t].removeAttribute('aria-current');
            }
        }
        var raf = 0;
        track.addEventListener('scroll', function () {
            if (raf) return;
            raf = requestAnimationFrame(function () { raf = 0; mark(); });
        }, { passive: true });
        for (var t = 0; t < thumbs.length; t++) {
            thumbs[t].addEventListener('click', function (e) {
                e.preventDefault();
                go(parseInt(this.getAttribute('data-index'), 10) || 0);
            });
        }
        function checkedColor() {
            var el = form && form.querySelector('input[name="color"]:checked');
            return el ? el.value : '';
        }
        if (form) {
            form.addEventListener('change', function (e) {
                if (e.target && e.target.name === 'color') go(indexOfColor(e.target.value));
            });
        }
        // Buka langsung di gambar warna yang terpilih (misalnya dari ?warna=sage).
        go(Math.max(0, indexOfColor(checkedColor())), true);
        mark();
    }

    // Login admin: tombol Lihat/Sembunyikan password (tanpa JS, kolom tetap tersembunyi biasa).
    function initPassToggle() {
        var btn = document.getElementById('pass-toggle');
        var input = document.getElementById('password');
        if (!btn || !input) return;
        btn.hidden = false;
        btn.addEventListener('click', function () {
            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            btn.textContent = show ? 'Sembunyikan' : 'Lihat';
            btn.setAttribute('aria-pressed', show ? 'true' : 'false');
            input.focus();
        });
        input.form.addEventListener('submit', function () { input.type = 'password'; });
    }

    // Detail produk di ponsel (wireframe hal. 19 dan 20): bar tombol beli menempel di bawah,
    // dan pilihan warna/ukuran/jumlah dibuka sebagai lembar dari bawah. Di layar lebar,
    // tombol yang sama hanya menggulir ke form. Form, CSRF, dan validasi server tidak berubah.
    function initVariantSheet() {
        var sheet = document.getElementById('vsheet');
        var form = document.getElementById('buy-form');
        if (!sheet || !form) return;
        var dim = document.getElementById('vsheet-dim');
        var closeBtn = document.getElementById('vsheet-close');
        var bar = document.getElementById('pd-bar');
        var pick = document.getElementById('pd-pick');
        var mq = window.matchMedia('(max-width: 899px)');
        var opener = null;
        if (bar) bar.hidden = false;
        if (pick) pick.hidden = false;

        function labelOf(name) {
            var el = form.querySelector('input[name="' + name + '"]:checked');
            return el ? el.parentNode.querySelector('span').firstChild.textContent.trim() : '-';
        }
        function summary() {
            var v = document.getElementById('pick-variant');
            var s = document.getElementById('pick-stock');
            var val = document.getElementById('stock-value');
            if (v) v.textContent = labelOf('size') + ', ' + labelOf('color');
            if (s && val) s.textContent = val.textContent.trim();
            var color = form.querySelector('input[name="color"]:checked');
            var src = color && color.getAttribute('data-img');
            var thumb = document.getElementById('vsheet-img');
            if (src && thumb && thumb.getAttribute('src') !== src) thumb.src = src;
        }
        function isOpen() { return sheet.classList.contains('is-open'); }
        function open(e) {
            opener = e && e.currentTarget;
            if (!mq.matches) {
                form.scrollIntoView({ behavior: 'smooth', block: 'start' });
                var first = form.querySelector('input:checked, input');
                if (first) first.focus({ preventScroll: true });
                return;
            }
            sheet.classList.add('is-open');
            sheet.setAttribute('role', 'dialog');
            sheet.setAttribute('aria-modal', 'true');
            if (dim) dim.hidden = false;
            document.body.classList.add('sheet-open');
            if (closeBtn) closeBtn.focus();
        }
        function close() {
            if (!isOpen()) return;
            sheet.classList.remove('is-open');
            sheet.removeAttribute('role');
            sheet.removeAttribute('aria-modal');
            if (dim) dim.hidden = true;
            document.body.classList.remove('sheet-open');
            if (opener && document.contains(opener)) opener.focus();
        }

        document.querySelectorAll('[data-open-sheet]').forEach(function (b) { b.addEventListener('click', open); });
        if (closeBtn) closeBtn.addEventListener('click', close);
        if (dim) dim.addEventListener('click', close);
        document.addEventListener('keydown', function (e) {
            if (!isOpen()) return;
            if (e.key === 'Escape') { e.preventDefault(); close(); return; }
            if (e.key === 'Tab') {
                // Fokus tetap di dalam lembar selama terbuka.
                var f = Array.prototype.filter.call(sheet.querySelectorAll('button, a[href], input, select'), function (el) { return !el.disabled && el.offsetParent !== null; });
                if (!f.length) return;
                if (e.shiftKey && document.activeElement === f[0]) { e.preventDefault(); f[f.length - 1].focus(); }
                else if (!e.shiftKey && document.activeElement === f[f.length - 1]) { e.preventDefault(); f[0].focus(); }
            }
        });
        var onWidth = function () { if (!mq.matches) close(); };
        if (mq.addEventListener) mq.addEventListener('change', onWidth); else if (mq.addListener) mq.addListener(onWidth);
        form.addEventListener('stockrender', summary);
        form.addEventListener('change', summary);
        summary();
    }

    // -----------------------------------------------------------------
    // 4. Tombol − / + di samping kotak jumlah.
    //    Tanpa JavaScript, kotak angka tetap bisa diketik (keranjang punya tombol Simpan).
    //    Di keranjang, perubahan jumlah langsung dikirim ke server (form biasa, ada CSRF).
    // -----------------------------------------------------------------
    function initSteppers() {
        document.querySelectorAll('.stepper').forEach(function (box) {
            var input = box.querySelector('input[type="number"]');
            if (!input) return;
            var form = input.form;
            var autoSubmit = form && form.classList.contains('js-qty-form');
            var buttons = box.querySelectorAll('[data-step]');

            function limits() {
                var min = parseInt(input.min, 10); if (isNaN(min)) min = 1;
                var max = parseInt(input.max, 10); if (isNaN(max)) max = 99;
                return { min: min, max: Math.max(min, max) };
            }
            function sync() {
                var l = limits();
                var v = parseInt(input.value, 10);
                buttons.forEach(function (b) {
                    var step = parseInt(b.getAttribute('data-step'), 10);
                    b.disabled = isNaN(v) ? false : (step < 0 ? v <= l.min : v >= l.max);
                });
            }
            buttons.forEach(function (b) {
                b.addEventListener('click', function () {
                    var l = limits();
                    var v = parseInt(input.value, 10);
                    if (isNaN(v)) v = l.min;
                    var next = Math.min(l.max, Math.max(l.min, v + parseInt(b.getAttribute('data-step'), 10)));
                    if (next === v) return;
                    input.value = String(next);
                    sync();
                    if (autoSubmit) {
                        buttons.forEach(function (x) { x.disabled = true; });
                        form.submit();
                    }
                });
            });
            input.addEventListener('input', sync);
            if (autoSubmit) {
                input.addEventListener('change', function () {
                    var v = parseInt(input.value, 10);
                    if (!isNaN(v) && v >= 1) form.submit();
                });
            }
            // Batas maksimum di halaman produk bisa berubah saat stok diperbarui.
            if ('MutationObserver' in window) {
                new MutationObserver(sync).observe(input, { attributes: true, attributeFilter: ['max'] });
            }
            sync();
        });
    }

    // -----------------------------------------------------------------
    // 5. Katalog: pilihan "Urutkan" langsung diterapkan (tanpa JS: tombol Terapkan)
    // -----------------------------------------------------------------
    function initAutoSubmit() {
        document.querySelectorAll('select.js-autosubmit, input.js-autosubmit').forEach(function (sel) {
            sel.addEventListener('change', function () { if (sel.form) sel.form.submit(); });
        });
        // Panel filter katalog: terbuka di layar lebar, tertutup di ponsel kecuali ada filter aktif.
        var filterBox = document.getElementById('cat-filter');
        if (filterBox && window.matchMedia('(min-width: 900px)').matches) filterBox.open = true;
    }

    // -----------------------------------------------------------------
    // 6. Header: menu Kategori dan keranjang mini (hanya layar lebar).
    //    Tanpa JavaScript tombol Kategori tetap tersembunyi dan ikon keranjang
    //    tetap tautan biasa ke /keranjang.
    // -----------------------------------------------------------------
    var wide = window.matchMedia ? window.matchMedia('(min-width: 900px)') : { matches: true };
    var closeHeaderPanels = function () {};

    function initHeaderPanels() {
        var megaBtn = document.getElementById('mega-btn');
        var mega = document.getElementById('mega');
        var dim = document.getElementById('mega-dim');
        var cartBtn = document.getElementById('cart-btn');
        var mini = document.getElementById('mini-cart');

        function setMega(open, focusBack) {
            if (!mega) return;
            mega.hidden = !open;
            if (dim) dim.hidden = !open;
            megaBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (open) {
                setMini(false);
                var sel = mega.querySelector('.mtab[aria-selected="true"]');
                if (sel) sel.focus();
            } else if (focusBack) {
                megaBtn.focus();
            }
        }
        function setMini(open, focusBack) {
            if (!mini) return;
            mini.hidden = !open;
            cartBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (open) {
                setMega(false);
            } else if (focusBack) {
                cartBtn.focus();
            }
        }
        closeHeaderPanels = function () { setMega(false); setMini(false); };

        if (megaBtn && mega) {
            megaBtn.hidden = false;
            megaBtn.addEventListener('click', function () { setMega(mega.hidden); });
            document.getElementById('mega-close').addEventListener('click', function () { setMega(false, true); });
            if (dim) dim.addEventListener('click', function () { setMega(false); });

            var tabs = Array.prototype.slice.call(mega.querySelectorAll('.mtab'));
            var pick = function (tab) {
                tabs.forEach(function (t) {
                    var on = t === tab;
                    t.setAttribute('aria-selected', on ? 'true' : 'false');
                    t.tabIndex = on ? 0 : -1;
                    document.getElementById(t.getAttribute('aria-controls')).hidden = !on;
                });
            };
            tabs.forEach(function (t, i) {
                t.addEventListener('click', function () { pick(t); });
                t.addEventListener('mouseenter', function () { pick(t); });
                t.addEventListener('keydown', function (e) {
                    var next = null;
                    if (e.key === 'ArrowDown') next = tabs[(i + 1) % tabs.length];
                    if (e.key === 'ArrowUp') next = tabs[(i - 1 + tabs.length) % tabs.length];
                    if (e.key === 'Home') next = tabs[0];
                    if (e.key === 'End') next = tabs[tabs.length - 1];
                    if (next) { e.preventDefault(); pick(next); next.focus(); }
                });
            });
        }

        if (cartBtn && mini) {
            cartBtn.addEventListener('click', function (e) {
                if (!wide.matches) return; // di ponsel langsung ke halaman keranjang
                e.preventDefault();
                setMini(mini.hidden);
            });
            // Panel yang terbuka setelah "Lihat cepat" tidak ikut terbuka lagi saat halaman dimuat ulang.
            if (!mini.hidden && window.history && history.replaceState && /[?&]keranjang=baru/.test(location.search)) {
                history.replaceState(null, '', location.pathname);
            }
            if (!mini.hidden && !wide.matches) setMini(false);
        }

        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') return;
            if (mega && !mega.hidden) setMega(false, true);
            if (mini && !mini.hidden) setMini(false, true);
        });
        document.addEventListener('click', function (e) {
            if (mini && !mini.hidden && !mini.contains(e.target) && !cartBtn.contains(e.target)) setMini(false);
            if (mega && !mega.hidden && !mega.contains(e.target) && !megaBtn.contains(e.target) && e.target !== dim) setMega(false);
        });
    }

    // -----------------------------------------------------------------
    // 7. Beranda: banner geser manual (tidak berputar sendiri) dan "Lihat … lainnya"
    // -----------------------------------------------------------------
    function initCarousel() {
        var car = document.getElementById('carousel');
        if (!car) return;
        var slides = car.querySelectorAll('.slide');
        var dots = car.querySelectorAll('.dot');
        if (slides.length < 2) return;
        var idx = 0;
        function show(n) {
            idx = (n + slides.length) % slides.length;
            slides.forEach(function (s, i) { s.classList.toggle('is-on', i === idx); });
            dots.forEach(function (d, i) {
                d.classList.toggle('on', i === idx);
                if (i === idx) { d.setAttribute('aria-current', 'true'); } else { d.removeAttribute('aria-current'); }
            });
        }
        car.querySelectorAll('[data-slide]').forEach(function (b) {
            b.hidden = false;
            b.addEventListener('click', function () { show(idx + parseInt(b.getAttribute('data-slide'), 10)); });
        });
        car.querySelector('.dots').hidden = false;
        dots.forEach(function (d) {
            d.addEventListener('click', function () { show(parseInt(d.getAttribute('data-dot'), 10)); });
        });
    }

    function initShowMore() {
        var btn = document.getElementById('show-more');
        var grid = btn && document.getElementById(btn.getAttribute('aria-controls') || 'home-grid');
        if (!btn || !grid) return;
        btn.hidden = false;
        btn.addEventListener('click', function () {
            var firstExtra = grid.querySelector('.product-card[data-extra] a');
            grid.classList.add('show-all');
            btn.hidden = true;
            if (firstExtra) firstExtra.focus();
        });
    }

    // -----------------------------------------------------------------
    // 8. Beranda: jendela "Lihat cepat". Pilihan ukuran/warna dan jumlah dikirim
    //    lewat form biasa ke /keranjang/tambah (CSRF + validasi server tetap berlaku).
    //    Stok dibaca dari /api/stok/{id}; jika gagal, tidak ditampilkan sebagai 0.
    // -----------------------------------------------------------------
    function initQuickView() {
        var dlg = document.getElementById('quick-view');
        var dataEl = document.getElementById('quick-data');
        if (!dlg || !dataEl || typeof dlg.showModal !== 'function') return;
        var products = {};
        try { JSON.parse(dataEl.textContent || '[]').forEach(function (p) { products[p.id] = p; }); } catch (e) { return; }

        var $ = function (id) { return document.getElementById(id); };
        var form = $('qv-form'), qty = $('qv-qty'), add = $('qv-add'), stockEl = $('qv-stock');
        var rp = function (n) { return 'Rp' + Number(n).toLocaleString('id-ID'); };
        var current = null, variants = null, failed = false, token = 0;

        function radios(box, name, items) {
            box.textContent = '';
            items.forEach(function (it, i) {
                var label = document.createElement('label');
                label.className = 'option';
                var input = document.createElement('input');
                input.type = 'radio'; input.name = name; input.value = it.code; input.checked = i === 0;
                var span = document.createElement('span');
                span.textContent = it.label;
                label.appendChild(input); label.appendChild(span);
                box.appendChild(label);
            });
        }
        function picked(name) {
            var el = form.querySelector('input[name="' + name + '"]:checked');
            return el ? el.value : '';
        }
        function showImage(src, label) {
            var ai = src.indexOf('/produk/foto/') !== -1;
            var img = $('qv-img');
            if (img.getAttribute('src') !== src) img.src = src;
            img.alt = label + (ai ? ' (gambar AI)' : ' (gambar ilustrasi)');
            $('qv-note').textContent = ai ? 'Gambar dibuat dengan AI, bukan foto produk asli.' : 'Gambar ilustrasi kategori, belum foto produk asli.';
        }
        function render() {
            var p = current, size = picked('size'), color = picked('color');
            var colorItem = p.colors.filter(function (c) { return c.code === color; })[0] || {};
            var colorLabel = colorItem.label || '';
            $('qv-color-lbl').textContent = 'Warna: ' + colorLabel;
            showImage(colorItem.img || p.img, p.name + (colorLabel ? ' warna ' + colorLabel : ''));
            // Ukuran yang habis untuk warna ini tidak bisa dipilih; jika yang terpilih habis, pindah ke yang masih ada.
            if (variants && !failed && (variants[size + '_' + color] || 0) <= 0) {
                var firstOk = Array.prototype.filter.call(form.querySelectorAll('#qv-sizes input'), function (i) { return (variants[i.value + '_' + color] || 0) > 0; })[0];
                if (firstOk) { firstOk.checked = true; size = firstOk.value; }
            }
            form.querySelectorAll('#qv-sizes .option').forEach(function (label) {
                var input = label.querySelector('input');
                var code = input.value;
                var out = variants && !failed && (variants[code + '_' + color] || 0) <= 0;
                label.classList.toggle('is-empty', !!out);
                input.disabled = !!out && !input.checked;
                var tag = label.querySelector('small');
                if (out && !tag) { tag = document.createElement('small'); tag.textContent = 'habis'; label.querySelector('span').appendChild(tag); }
                if (!out && tag) tag.remove();
            });
            $('qv-detail').href = '/produk/' + encodeURIComponent(p.id) + '?ukuran=' + encodeURIComponent(size) + '&warna=' + encodeURIComponent(color);
            var n = variants ? (variants[size + '_' + color] || 0) : null;
            stockEl.className = 'qv-stock';
            if (failed) {
                stockEl.textContent = 'Tidak dapat memperbarui stok. Saat checkout, stok dicek ulang.';
                stockEl.classList.add('err');
            } else if (n === null) {
                stockEl.textContent = 'Memuat stok…';
            } else if (n <= 0) {
                stockEl.textContent = 'Stok bersama: habis';
                stockEl.classList.add('zero');
            } else {
                stockEl.textContent = 'Stok bersama: ' + n;
            }
            var max = n && n > 0 ? Math.min(99, n) : 99;
            qty.max = String(max);
            if (parseInt(qty.value, 10) > max) qty.value = String(max);
            var soldOut = !failed && n !== null && n <= 0;
            add.disabled = soldOut;
            add.textContent = soldOut ? 'Varian ini habis' : 'Masukkan keranjang · ' + rp((parseInt(qty.value, 10) || 1) * p.price);
        }
        function open(id) {
            var p = products[id];
            if (!p) return;
            closeHeaderPanels();
            current = p; variants = null; failed = false;
            var mine = ++token;
            $('qv-pid').value = p.id;
            $('qv-h').textContent = p.name;
            $('qv-code').textContent = p.cat + ' · Kode ' + p.id;
            $('qv-price').textContent = rp(p.price);
            $('qv-size-lbl').textContent = 'Ukuran (' + p.sizeLabel + ')';
            radios($('qv-colors'), 'color', p.colors);
            radios($('qv-sizes'), 'size', p.sizes);
            qty.value = '1';
            render();
            dlg.showModal();
            fetch('/api/stok/' + encodeURIComponent(p.id), { headers: { 'Accept': 'application/json' }, cache: 'no-store', credentials: 'same-origin' })
                .then(function (res) { return res.json().then(function (d) { return { ok: res.ok, d: d }; }); })
                .then(function (r) {
                    if (mine !== token) return;
                    if (!r.ok || !r.d || r.d.ok !== true) throw new Error('stok');
                    variants = r.d.variants || {};
                    render();
                })
                .catch(function () { if (mine === token) { failed = true; render(); } });
        }

        form.addEventListener('change', render);
        qty.addEventListener('input', render);
        form.querySelectorAll('.step-btn').forEach(function (b) { b.addEventListener('click', function () { setTimeout(render, 0); }); });
        $('qv-close').addEventListener('click', function () { dlg.close(); });
        dlg.addEventListener('click', function (e) { if (e.target === dlg) dlg.close(); }); // klik di luar kotak
        form.addEventListener('submit', function () { add.disabled = true; add.textContent = 'Menambahkan…'; });

        document.querySelectorAll('.qvb[data-quick]').forEach(function (b) {
            b.hidden = false;
            b.addEventListener('click', function () { open(b.getAttribute('data-quick')); });
        });
    }

    document.documentElement.classList.add('js');
    document.addEventListener('DOMContentLoaded', function () {
        initStockPolling();
        initCheckoutForm();
        initSubmitOnce();
        initPassToggle();
        initGallery();
        initVariantSheet();
        initSteppers();
        initAutoSubmit();
        initHeaderPanels();
        initCarousel();
        initShowMore();
        initQuickView();
    });
})();
