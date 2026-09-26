import { useEffect, useRef, useState } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { api, ApiError } from '@/lib/api';
import { sessionKey } from '@/auth/session';
import type { ScanResult, ScansPoll, Session, UsbStatus } from '@/types/api';

/** Same cadence as the Livewire till's poll. */
const INTERVAL_MS = 900;

/**
 * Codes sent by a paired phone (ScanQueue) and the USB scanner state, as
 * PointDeVente::recupererScans() fetched them: one poll, both answers.
 * Paused while the tab is hidden; never two polls in flight.
 */
export function usePhoneScans(onScan: (result: ScanResult) => void, initialUsb: UsbStatus | undefined) {
    const [usb, setUsb] = useState<UsbStatus | undefined>(initialUsb);
    const onScanRef = useRef(onScan);
    onScanRef.current = onScan;
    const queryClient = useQueryClient();

    useEffect(() => setUsb(initialUsb), [initialUsb]);

    useEffect(() => {
        let busy = false;
        let stopped = false;

        const tick = async () => {
            if (busy || document.hidden) return;
            busy = true;
            try {
                const poll = await api<ScansPoll>('/pos/scans');
                if (stopped) return;
                setUsb(poll.usb);
                poll.scans.forEach((scan) => onScanRef.current(scan));
            } catch (error) {
                // Session gone: back to login. Anything else: retried next tick.
                if (error instanceof ApiError && (error.kind === 'auth' || error.kind === 'csrf')) {
                    queryClient.setQueryData<Session>(sessionKey, (s) => (s ? { ...s, user: null } : s));
                }
            } finally {
                busy = false;
            }
        };

        const timer = window.setInterval(tick, INTERVAL_MS);
        return () => {
            stopped = true;
            window.clearInterval(timer);
        };
    }, [queryClient]);

    return usb;
}
