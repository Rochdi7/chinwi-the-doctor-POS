import { useEffect, useRef, useState } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { api, ApiError } from '@/lib/api';
import { sessionKey } from '@/auth/session';
import type { ScanResult, ScansPoll, Session, UsbStatus } from '@/types/api';

/** While a phone is in use: the Livewire till's cadence. */
const ACTIVE_MS = 900;
/** Otherwise: the USB state only changes every 10 s server-side anyway. */
const IDLE_MS = 2500;
/** A phone counts as in use this long after its last scan. */
const ACTIVE_FOR_MS = 60_000;

/**
 * Codes sent by a paired phone (ScanQueue) and the USB scanner state, as
 * PointDeVente::recupererScans() fetched them: one poll, both answers.
 *
 * `php artisan serve` answers one request at a time, so a fast poll that
 * nobody needs delays the scans that matter. The poll runs fast only while
 * a phone is being paired or has just sent something. Paused while the tab
 * is hidden; never two polls in flight.
 */
export function usePhoneScans(onScan: (result: ScanResult) => void, initialUsb: UsbStatus | undefined, pairing: boolean) {
    const [usb, setUsb] = useState<UsbStatus | undefined>(initialUsb);
    const onScanRef = useRef(onScan);
    onScanRef.current = onScan;
    const pairingRef = useRef(pairing);
    pairingRef.current = pairing;
    const queryClient = useQueryClient();

    useEffect(() => setUsb(initialUsb), [initialUsb]);

    useEffect(() => {
        let stopped = false;
        let timer: number | undefined;
        let lastPhoneScan = 0;

        const schedule = () => {
            if (stopped) return;
            const active = pairingRef.current || Date.now() - lastPhoneScan < ACTIVE_FOR_MS;
            timer = window.setTimeout(tick, active ? ACTIVE_MS : IDLE_MS);
        };

        const tick = async () => {
            if (document.hidden) return schedule();
            try {
                const poll = await api<ScansPoll>('/pos/scans');
                if (stopped) return;
                setUsb(poll.usb);
                if (poll.scans.length > 0) lastPhoneScan = Date.now();
                poll.scans.forEach((scan) => onScanRef.current(scan));
            } catch (error) {
                // Session gone: back to login. Anything else: retried next tick.
                if (error instanceof ApiError && (error.kind === 'auth' || error.kind === 'csrf')) {
                    queryClient.setQueryData<Session>(sessionKey, (s) => (s ? { ...s, user: null } : s));
                }
            }
            schedule();
        };

        schedule();
        return () => {
            stopped = true;
            window.clearTimeout(timer);
        };
    }, [queryClient]);

    return usb;
}
