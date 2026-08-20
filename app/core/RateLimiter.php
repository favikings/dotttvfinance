<?php

declare(strict_types=1);

/**
 * File-based login rate limiter — 5 attempts per 15 minutes per key
 * (Auth uses "ip|email" as the key). Chose file-based over a DB table
 * because schema.sql is the finalized source of truth for tables (Tech
 * Spec §1) and adding a login_attempts table wasn't asked for; one small
 * JSON file per key under storage/rate_limits/ needs no schema change and
 * is more than enough at this app's traffic volume.
 */
class RateLimiter
{
    private const MAX_ATTEMPTS = 5;
    private const WINDOW_SECONDS = 15 * 60;

    public static function tooManyAttempts(string $key): bool
    {
        return count(self::recentTimestamps($key)) >= self::MAX_ATTEMPTS;
    }

    public static function retryAfterSeconds(string $key): int
    {
        $timestamps = self::recentTimestamps($key);

        if ($timestamps === []) {
            return 0;
        }

        return max(0, self::WINDOW_SECONDS - (time() - min($timestamps)));
    }

    public static function recordFailure(string $key): void
    {
        self::withLockedFile($key, function (array $timestamps): array {
            $timestamps[] = time();
            return self::pruneOld($timestamps);
        });
    }

    public static function clear(string $key): void
    {
        $path = self::pathFor($key);
        if (is_file($path)) {
            unlink($path);
        }
    }

    private static function recentTimestamps(string $key): array
    {
        $path = self::pathFor($key);

        if (!is_file($path)) {
            return [];
        }

        $raw = file_get_contents($path);
        $timestamps = is_string($raw) ? json_decode($raw, true) : null;

        return self::pruneOld(is_array($timestamps) ? $timestamps : []);
    }

    private static function pruneOld(array $timestamps): array
    {
        $cutoff = time() - self::WINDOW_SECONDS;
        return array_values(array_filter($timestamps, static fn ($t) => is_int($t) && $t >= $cutoff));
    }

    private static function withLockedFile(string $key, callable $mutate): void
    {
        $dir = dirname(self::pathFor($key));
        if (!is_dir($dir)) {
            mkdir($dir, 0700, true);
        }

        $handle = fopen(self::pathFor($key), 'c+');
        if ($handle === false) {
            return;
        }

        flock($handle, LOCK_EX);

        $raw = stream_get_contents($handle);
        $timestamps = is_string($raw) ? json_decode($raw, true) : null;
        $timestamps = self::pruneOld(is_array($timestamps) ? $timestamps : []);

        $timestamps = $mutate($timestamps);

        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, json_encode(array_values($timestamps)));
        fflush($handle);

        flock($handle, LOCK_UN);
        fclose($handle);
    }

    private static function pathFor(string $key): string
    {
        return STORAGE_PATH . '/rate_limits/' . hash('sha256', $key) . '.json';
    }
}
