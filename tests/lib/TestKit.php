<?php

declare(strict_types=1);

/**
 * Alat bantu tes sederhana (tanpa PHPUnit agar mudah dijalankan pemula).
 */
final class TestKit
{
    public static int $pass = 0;
    public static int $fail = 0;
    public static int $skip = 0;
    /** @var list<array{id:string,name:string,result:string,note:string}> */
    public static array $rows = [];

    public static function run(string $id, string $name, callable $fn): void
    {
        try {
            $note = $fn();
            self::$pass++;
            self::$rows[] = ['id' => $id, 'name' => $name, 'result' => 'LULUS', 'note' => is_string($note) ? $note : ''];
            echo "  [LULUS] {$id} {$name}" . (is_string($note) && $note !== '' ? " — {$note}" : '') . PHP_EOL;
        } catch (SkipTest $e) {
            self::$skip++;
            self::$rows[] = ['id' => $id, 'name' => $name, 'result' => 'LEWATI', 'note' => $e->getMessage()];
            echo "  [LEWATI] {$id} {$name} — {$e->getMessage()}" . PHP_EOL;
        } catch (\Throwable $e) {
            self::$fail++;
            self::$rows[] = ['id' => $id, 'name' => $name, 'result' => 'GAGAL', 'note' => $e->getMessage()];
            echo "  [GAGAL] {$id} {$name}" . PHP_EOL . "          " . get_class($e) . ': ' . $e->getMessage() . PHP_EOL;
        }
    }

    public static function assert(bool $cond, string $message): void
    {
        if (!$cond) {
            throw new AssertionFailed($message);
        }
    }

    public static function same(mixed $expected, mixed $actual, string $label): void
    {
        if ($expected !== $actual) {
            throw new AssertionFailed("{$label}: diharapkan " . var_export($expected, true) . ', didapat ' . var_export($actual, true));
        }
    }

    /** @param class-string<\Throwable> $class */
    public static function throws(string $class, callable $fn, ?callable $check = null): \Throwable
    {
        try {
            $fn();
        } catch (\Throwable $e) {
            if (!$e instanceof $class) {
                throw new AssertionFailed("Diharapkan {$class}, didapat " . get_class($e) . ': ' . $e->getMessage());
            }
            if ($check) {
                $check($e);
            }
            return $e;
        }
        throw new AssertionFailed("Diharapkan {$class}, tetapi tidak ada exception.");
    }

    public static function summary(string $title): int
    {
        echo PHP_EOL . str_repeat('=', 60) . PHP_EOL;
        echo "{$title}: " . self::$pass . ' lulus, ' . self::$fail . ' gagal, ' . self::$skip . ' dilewati' . PHP_EOL;
        echo 'Waktu: ' . date('Y-m-d H:i:s') . ' WIB' . PHP_EOL;
        return self::$fail > 0 ? 1 : 0;
    }

    /** Simpan hasil sebagai Markdown untuk lampiran bukti uji. */
    public static function writeReport(string $file, string $title): void
    {
        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $md = "# {$title}\n\nDijalankan: " . date('Y-m-d H:i:s') . " WIB · PHP " . PHP_VERSION . "\n\n";
        $md .= "| ID | Skenario | Hasil | Catatan |\n|---|---|---|---|\n";
        foreach (self::$rows as $r) {
            $md .= '| ' . $r['id'] . ' | ' . $r['name'] . ' | ' . $r['result'] . ' | ' . str_replace(['|', "\n"], ['/', ' '], $r['note']) . " |\n";
        }
        $md .= "\nRingkasan: " . self::$pass . ' lulus, ' . self::$fail . ' gagal, ' . self::$skip . " dilewati.\n";
        file_put_contents($file, $md);
    }
}

final class AssertionFailed extends \RuntimeException
{
}

final class SkipTest extends \RuntimeException
{
}

/**
 * Klien HTTP kecil dengan cookie jar (meniru satu browser).
 * Memakai stream bawaan PHP agar tidak butuh ekstensi curl.
 */
final class Browser
{
    /** @var array<string,string> */
    private array $cookies = [];
    public int $status = 0;
    /** @var array<string,string> */
    public array $headers = [];
    public string $body = '';

    public function __construct(private readonly string $base)
    {
    }

    public function get(string $path): self
    {
        return $this->request('GET', $path);
    }

    public function post(string $path, array $fields): self
    {
        return $this->request('POST', $path, http_build_query($fields));
    }

    public function request(string $method, string $path, ?string $body = null): self
    {
        $headers = ['Accept: text/html,application/json'];
        if ($this->cookies) {
            $headers[] = 'Cookie: ' . implode('; ', array_map(static fn ($k, $v) => "{$k}={$v}", array_keys($this->cookies), $this->cookies));
        }
        if ($body !== null) {
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        }
        $ctx = stream_context_create(['http' => [
            'method' => $method, 'header' => implode("\r\n", $headers), 'content' => $body ?? '',
            'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 15,
        ]]);
        $http_response_header = [];
        $res = @file_get_contents($this->base . $path, false, $ctx);
        if ($res === false) {
            throw new \RuntimeException("Tidak dapat menghubungi {$this->base}{$path}. Apakah server sudah dijalankan?");
        }
        $this->body = $res;
        $this->headers = [];
        foreach ($http_response_header as $line) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $m)) {
                $this->status = (int) $m[1];
                $this->headers = [];
                continue;
            }
            [$k, $v] = array_pad(explode(':', $line, 2), 2, '');
            $k = strtolower(trim($k));
            $v = trim($v);
            if ($k === 'set-cookie') {
                $pair = explode(';', $v, 2)[0];
                [$ck, $cv] = array_pad(explode('=', $pair, 2), 2, '');
                $this->cookies[trim($ck)] = trim($cv);
            }
            $this->headers[$k] = $v;
        }
        return $this;
    }

    public function location(): string
    {
        return $this->headers['location'] ?? '';
    }

    /** Ikuti redirect (maksimal 5 kali). */
    public function follow(): self
    {
        for ($i = 0; $i < 5 && in_array($this->status, [301, 302, 303], true); $i++) {
            $this->get($this->location());
        }
        return $this;
    }

    public function csrf(): string
    {
        if (!preg_match('/name="_csrf" value="([a-f0-9]{64})"/', $this->body, $m)) {
            throw new AssertionFailed('Token CSRF tidak ditemukan di halaman.');
        }
        return $m[1];
    }

    public function json(): array
    {
        $d = json_decode($this->body, true);
        if (!is_array($d)) {
            throw new AssertionFailed('Respons bukan JSON: ' . substr($this->body, 0, 200));
        }
        return $d;
    }
}
