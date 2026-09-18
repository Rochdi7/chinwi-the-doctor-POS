<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * The bridge between a device used as a scanner (a phone, a tablet, another
 * PC) and the till screen.
 *
 * The scanner device pushes codes here, the open till drains them a few
 * times a second. Shared hosting has no WebSocket server, so this is a short
 * queue in the cache rather than a broadcast: it needs nothing installed,
 * and a code that nobody collects disappears on its own.
 *
 * Queues are keyed by a till code, not by user, so a second cashier on their
 * own account can still feed the till they are standing at.
 */
class ScanQueue
{
    /** Long enough to survive a slow poll, short enough that a forgotten scan never resurfaces. */
    private const TTL = 60;

    /** A till that never polls must not grow an endless backlog. */
    private const MAX = 20;

    /** How long a till keeps the same code, so a reload does not unpair the phone. */
    private const TILL_TTL = 86400;

    public static function push(string $till, string $code): void
    {
        $code = trim($code);

        if ($code === '' || $till === '') {
            return;
        }

        $queue = static::pending($till);
        $queue[] = ['code' => $code, 'at' => microtime(true)];

        Cache::put(static::key($till), array_slice($queue, -self::MAX), self::TTL);
    }

    /**
     * Take everything waiting. Draining rather than reading keeps a scan
     * from being added twice if the till polls again before the next one.
     *
     * @return array<int, string>
     */
    public static function drain(string $till): array
    {
        $queue = static::pending($till);

        Cache::forget(static::key($till));

        return array_column($queue, 'code');
    }

    /**
     * The till a user is working at. Stable across reloads so the phone stays
     * paired, and per user so two cashiers never share a queue by accident.
     */
    public static function tillFor(int $userId): string
    {
        $key = 'scan-till.'.$userId;
        $till = Cache::get($key);

        if (! $till) {
            // Short and unguessable: it travels in a QR code and in a URL.
            $till = substr(bin2hex(random_bytes(8)), 0, 12);
        }

        // Touch it on every read so an open till never expires under itself.
        Cache::put($key, $till, self::TILL_TTL);

        return $till;
    }

    /** A scanner device may be handed an explicit till code by QR. */
    public static function isValid(string $till): bool
    {
        return (bool) preg_match('/^[a-f0-9]{12}$/', $till);
    }

    /** @return array<int, array{code: string, at: float}> */
    private static function pending(string $till): array
    {
        return Cache::get(static::key($till), []);
    }

    private static function key(string $till): string
    {
        return 'scan-queue.'.$till;
    }
}
