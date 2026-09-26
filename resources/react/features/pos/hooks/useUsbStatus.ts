import { useEffect, useState } from 'react';
import { api } from '@/lib/api';
import type { ScansPoll, UsbStatus } from '@/types/api';

/** Windows is only asked every 10 s server-side; no point asking more often. */
const INTERVAL_MS = 10_000;

/**
 * The USB scanner badge (App\Support\ScannerUsb): one light request every
 * 10 s, paused while the tab is hidden. Nothing else polls the server.
 */
export function useUsbStatus(initial: UsbStatus | undefined) {
    const [usb, setUsb] = useState<UsbStatus | undefined>(initial);

    useEffect(() => setUsb(initial), [initial]);

    useEffect(() => {
        let stopped = false;
        const tick = async () => {
            if (document.hidden) return;
            try {
                const poll = await api<ScansPoll>('/pos/scans');
                if (!stopped) setUsb(poll.usb);
            } catch {
                // Retried on the next tick; the badge simply keeps its last state.
            }
        };
        const timer = window.setInterval(tick, INTERVAL_MS);
        return () => {
            stopped = true;
            window.clearInterval(timer);
        };
    }, []);

    return usb;
}
