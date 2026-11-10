<?php

declare(strict_types=1);

namespace App;

use App\Data\CentralDataService;
use App\Data\DataServiceFactory;

/**
 * Penyimpan konfigurasi dan layanan bersama (service locator sederhana).
 * Sengaja dibuat kecil agar mudah dipahami pemula.
 */
final class App
{
    private static string $root = '';
    /** @var array<string,mixed> */
    private static array $config = [];
    private static ?CentralDataService $data = null;

    /** @param array<string,mixed> $config */
    public static function init(string $root, array $config): void
    {
        self::$root = $root;
        self::$config = $config;
        self::$data = null;
    }

    public static function root(): string
    {
        return self::$root;
    }

    public static function config(string $key, mixed $default = null): mixed
    {
        $value = self::$config;
        foreach (explode('.', $key) as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return $default;
            }
            $value = $value[$part];
        }
        return $value;
    }

    /** @return array<string,mixed> */
    public static function allConfig(): array
    {
        return self::$config;
    }

    /** Layanan data (adapter Redis lokal ATAU adapter pusat Kelompok 6), dibuat saat pertama dipakai. */
    public static function data(): CentralDataService
    {
        if (self::$data === null) {
            self::$data = DataServiceFactory::fromConfig(self::$config);
        }
        return self::$data;
    }

    /** Dipakai oleh tes untuk menyuntikkan adapter tertentu. */
    public static function setData(CentralDataService $data): void
    {
        self::$data = $data;
    }
}
