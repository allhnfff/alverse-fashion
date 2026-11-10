<?php

declare(strict_types=1);

namespace App\Data;

/** Memilih adapter berdasarkan DATA_DRIVER di .env. Controller tidak tahu adapter mana yang dipakai. */
final class DataServiceFactory
{
    public static function fromConfig(array $config): CentralDataService
    {
        $driver = $config['data']['driver'] ?? 'redis';

        if ($driver === 'central') {
            return new CentralHttpAdapter(
                (string) $config['central']['base_url'],
                (string) $config['central']['api_key'],
                (float) $config['central']['timeout'],
                (array) $config['central_endpoints'],
                (string) $config['branch']['id']
            );
        }

        $r = $config['redis'];
        $label = sprintf('Redis lokal %s:%d (db %d)', $r['host'], $r['port'], $r['database']);
        return new RedisLocalAdapter(
            RedisLocalAdapter::createClient($r),
            (array) $config['shipping'],
            (array) $config['branches'],
            $label
        );
    }
}
