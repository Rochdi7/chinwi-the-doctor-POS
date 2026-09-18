<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * The bridge between a phone used as a scanner and the till on the PC.
 *
 * The phone pushes codes here, the open till drains them a few times a
 * second. Shared hosting has no WebSocket server, so this is a short queue
 * in the cache rather than a broadcast: it needs nothing installed, and a
 * code that nobody collects disappears on its own.
 */
class ScanQueue
{
    /** Long enough to survive a slow poll, short enough that a forgotten scan never resurfaces. */
    private const TTL = 60;

    /** A till that never polls must not grow an endless backlog. */
    private const MAX = 20;

    /** One queue per user: two cashiers on two tills never cross. */
    public static function push(int $userId, string $code): void
    {
        $code = trim($code);

        if ($code === '') {
            return;
        }

        $queue = static::pending($userId);
        $queue[] = ['code' => $code, 'at' => microtime(true)];

        Cache::put(static::key($userId), array_slice($queue, -self::MAX), self::TTL);
    }

    /**
     * Take everything waiting. Draining rather than reading keeps a scan
     * from being added twice if the till polls again before the next one.
     *
     * @return array<int, string>
     */
    public static function drain(int $userId): array
    {
        $queue = static::pending($userId);

        Cache::forget(static::key($userId));

        return array_column($queue, 'code');
    }

    /** @return array<int, array{code: string, at: float}> */
    private static function pending(int $userId): array
    {
        return Cache::get(static::key($userId), []);
    }

    private static function key(int $userId): string
    {
        return 'scan-queue.'.$userId;
    }
}
