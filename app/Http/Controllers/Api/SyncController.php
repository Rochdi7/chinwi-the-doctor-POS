<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "Did anything change since I last asked?" for the React app.
 *
 * The same account is often open on several devices at once (the till PC,
 * a phone, the manager's laptop). A sale keyed on one must show on the
 * others without a reload: stock in the grid, the day's figures, the
 * alerts bell, the sales list.
 *
 * Shared hosting has no WebSocket server, so every open screen asks this
 * endpoint every few seconds. The answer is built from the audit log
 * (activity_logs), which every write already feeds through the observers:
 * one indexed range query on the primary key, no extra table, no extra
 * write on the hot path. The client keeps the last id it saw as a cursor
 * and refreshes only the screens that the changed models feed.
 */
class SyncController extends Controller
{
    /** More rows than this since the cursor: tell the client to refresh everything. */
    private const LIMIT = 200;

    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate(['since' => ['nullable', 'integer', 'min:0']]);

        $latest = (int) (ActivityLog::query()->max('id') ?? 0);
        $since = $data['since'] ?? null;

        // First call: start from now. Nothing to refresh, the screens have
        // just loaded.
        if ($since === null) {
            return response()->json(['cursor' => $latest, 'changes' => []]);
        }

        $since = (int) $since;

        if ($since === $latest) {
            return response()->json(['cursor' => $latest, 'changes' => []]);
        }

        // A cursor ahead of the log means the log was reset under the
        // client: refresh everything rather than guess.
        if ($since > $latest) {
            return response()->json(['cursor' => $latest, 'changes' => ['*']]);
        }

        $rows = ActivityLog::query()
            ->where('id', '>', $since)
            ->orderBy('id')
            ->limit(self::LIMIT + 1)
            ->get(['id', 'event', 'subject_type']);

        if ($rows->count() > self::LIMIT) {
            return response()->json(['cursor' => $latest, 'changes' => ['*']]);
        }

        $changes = $rows
            ->map(fn (ActivityLog $row) => $this->topic($row))
            ->unique()
            ->values()
            ->all();

        return response()->json([
            'cursor' => (int) $rows->last()->id,
            'changes' => $changes,
        ]);
    }

    /**
     * What a log line means for the screens: the model that changed
     * (Article, Invoice, Payment...), "settings" for the Paramètres page,
     * "print" for a PDF that was only printed (nothing to refresh but the
     * journal).
     */
    private function topic(ActivityLog $row): string
    {
        if (str_ends_with($row->event, '.printed')) {
            return 'print';
        }

        if ($row->event === 'settings.updated') {
            return 'settings';
        }

        return $row->subject_type ?? 'other';
    }
}
