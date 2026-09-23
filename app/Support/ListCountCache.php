<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

final class ListCountCache
{
    public static function remember(string $domain, array $filters, callable $count): int
    {
        ksort($filters);
        $ver = (int) Cache::get(self::versionKey($domain), 1);
        $key = $domain.'_list_count:'.$ver.':'.md5((string) json_encode($filters));

        return (int) Cache::remember($key, 120, $count);
    }

    public static function bump(string $domain): void
    {
        $key = self::versionKey($domain);
        if (! Cache::has($key)) {
            Cache::forever($key, 1);

            return;
        }

        Cache::increment($key);
    }

    private static function versionKey(string $domain): string
    {
        return $domain.'_list_ver';
    }
}
