import { useEffect } from 'react';
import { useQueryClient, type QueryClient } from '@tanstack/react-query';
import { api } from '@/lib/api';
import { useSession } from '@/auth/session';
import type { SyncPoll } from '@/types/api';

/**
 * Live sync between devices.
 *
 * The same account is often open on the till PC, a phone and the manager's
 * laptop at once. Every write on any of them lands in the audit log, and
 * GET /api/sync says which models changed since the last id this tab saw.
 * The matching queries are then marked stale and refetched, so the grid's
 * stock, the day's figures, the sales list and the bell follow within a
 * few seconds, without a reload.
 *
 * Polling, not WebSockets: the shop runs on shared hosting with no socket
 * server, and one indexed primary-key query every few seconds per open
 * screen costs nothing. The tab stops asking while hidden and asks at once
 * when it comes back, regains focus or gets its network back.
 */

/** Between two polls while someone is using this window. */
const INTERVAL_MS = 3_000;

/**
 * Visible but not focused (a second screen, a phone left on the counter):
 * slower, it catches up at once when used again. Each poll boots Laravel,
 * and a local till server may answer one request at a time.
 */
const IDLE_INTERVAL_MS = 10_000;

/** After a failed poll: back off, up to this, until the server answers again. */
const MAX_BACKOFF_MS = 30_000;

/**
 * Which queries each changed model feeds. Prefixes: ['/ventes'] also covers
 * ['/ventes', id] and ['/ventes', params]. Anything the log records also
 * shows in the journal, which is refreshed on every change.
 */
const TOPICS: Record<string, readonly (readonly string[])[]> = {
    Article: [['pos', 'articles'], ['pos', 'apercu'], ['/articles'], ['/pos/articles'], ['alertes']],
    Category: [['/categories'], ['pos', 'init'], ['/articles']],
    Client: [['/clients'], ['/clients/options'], ['pos', 'init'], ['dashboard']],
    Invoice: [['/ventes'], ['dashboard'], ['alertes'], ['pos', 'journee'], ['/clients']],
    InvoiceItem: [['/ventes'], ['pos', 'articles'], ['pos', 'apercu'], ['/articles'], ['/pos/articles'], ['alertes'], ['dashboard']],
    Payment: [['/reglements'], ['/ventes'], ['dashboard'], ['alertes'], ['pos', 'journee'], ['/caisse'], ['/clients']],
    CaisseMouvement: [['/caisse'], ['dashboard']],
    // Company name and currency travel with the session.
    settings: [['/parametres'], ['session']],
    print: [],
};

const JOURNAL: readonly (readonly string[])[] = [['/journal'], ['/journal/evenements']];

/** Refresh what the changed models feed; an unknown model refreshes everything. */
export function applyChanges(queryClient: QueryClient, changes: string[]): void {
    if (changes.length === 0) return;

    if (changes.includes('*') || changes.some((c) => !(c in TOPICS))) {
        void queryClient.invalidateQueries();
        return;
    }

    const keys = new Set<string>();
    for (const change of changes) {
        for (const key of [...TOPICS[change]!, ...JOURNAL]) keys.add(JSON.stringify(key));
    }
    for (const key of keys) {
        void queryClient.invalidateQueries({ queryKey: JSON.parse(key) as string[] });
    }
}

export function useLiveSync(enabled: boolean): void {
    const queryClient = useQueryClient();

    useEffect(() => {
        if (!enabled) return;

        let cursor: number | null = null;
        /** 0 = normal rhythm; otherwise the back-off after a failure. */
        let delay = 0;
        let timer = 0;
        let inFlight = false;
        let stopped = false;

        const schedule = (ms: number) => {
            window.clearTimeout(timer);
            timer = window.setTimeout(() => void tick(), ms);
        };

        const tick = async () => {
            if (stopped || inFlight) return;
            // A hidden tab waits for visibilitychange rather than polling.
            if (document.hidden) return;

            inFlight = true;
            try {
                const poll = await api<SyncPoll>('/sync', { query: { since: cursor } });
                if (stopped) return;
                if (cursor !== null) applyChanges(queryClient, poll.changes);
                cursor = poll.cursor;
                delay = 0;
            } catch {
                // Offline or the server is busy: try again later, more slowly.
                // A lost session is handled globally (App.onApiError).
                delay = Math.min(Math.max(delay * 2, INTERVAL_MS), MAX_BACKOFF_MS);
            } finally {
                inFlight = false;
                if (!stopped) schedule(delay || (document.hasFocus() ? INTERVAL_MS : IDLE_INTERVAL_MS));
            }
        };

        // Coming back to the tab or the network: ask right away.
        const now = () => {
            if (!document.hidden) schedule(0);
        };
        document.addEventListener('visibilitychange', now);
        window.addEventListener('focus', now);
        window.addEventListener('online', now);

        schedule(0);

        return () => {
            stopped = true;
            window.clearTimeout(timer);
            document.removeEventListener('visibilitychange', now);
            window.removeEventListener('focus', now);
            window.removeEventListener('online', now);
        };
    }, [enabled, queryClient]);
}

/** Mounted once, under the session: polls while someone is logged in. */
export function LiveSync() {
    const { user } = useSession();
    useLiveSync(user !== null);
    return null;
}
