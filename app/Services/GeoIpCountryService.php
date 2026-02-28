<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use MaxMind\Db\Reader;

class GeoIpCountryService
{
    public function lookup(string $ip): ?string
    {
        if ($this->isPrivateIp($ip)) {
            return null;
        }

        $ttl = config('locale.geoip_cache_ttl_seconds', 86400);

        return Cache::remember("geoip:{$ip}", $ttl, function () use ($ip) {
            return $this->query($ip);
        });
    }

    protected function query(string $ip): ?string
    {
        $dbPath = config('geoip.db_path');

        if (!$dbPath || !file_exists($dbPath)) {
            return null;
        }

        try {
            $reader = new Reader($dbPath);
            $record = $reader->get($ip);
            $reader->close();

            return $record['country']['iso_code'] ?? null;
        } catch (\Exception) {
            return null;
        }
    }

    protected function isPrivateIp(string $ip): bool
    {
        return !filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
    }
}
