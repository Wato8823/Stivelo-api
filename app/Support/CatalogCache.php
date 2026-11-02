<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Cache du catalogue public.
 *
 * Le cache "database" de Laravel ne supporte pas les tags : on versionne
 * plutôt les clés. Toute écriture du catalogue (administration) appelle
 * `flush()`, ce qui rend immédiatement obsolètes toutes les entrées
 * précédentes sans avoir à les énumérer.
 */
class CatalogCache
{
    private const VERSION_KEY = 'catalog.version';

    public static function version(): int
    {
        return (int) Cache::rememberForever(self::VERSION_KEY, fn () => 1);
    }

    public static function key(string $suffix): string
    {
        return 'catalog.v'.self::version().'.'.$suffix;
    }

    /**
     * @template TValue
     *
     * @param  Closure(): TValue  $callback
     * @return TValue
     */
    public static function remember(string $suffix, Closure $callback, int $ttlSeconds = 600): mixed
    {
        return Cache::remember(self::key($suffix), $ttlSeconds, $callback);
    }

    public static function flush(): void
    {
        Cache::forever(self::VERSION_KEY, self::version() + 1);
    }
}
