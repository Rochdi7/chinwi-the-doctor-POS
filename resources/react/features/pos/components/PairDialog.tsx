import { useEffect, useState } from 'react';
import QRCode from 'qrcode';
import { Copy, Check } from 'lucide-react';
import { useT } from '@/auth/session';
import { Dialog } from '@/components/ui/Dialog';

/**
 * Pair a phone with this till: the phone opens the existing scanner page
 * (App\Filament\Pages\ScannerTelephone) with this till's code, and its
 * scans reach the cart through ScanQueue. The QR is drawn locally, so this
 * works without internet.
 */
export function PairDialog({ open, url, onClose }: { open: boolean; url: string | undefined; onClose: () => void }) {
    const t = useT();
    const [qr, setQr] = useState<string | null>(null);
    const [copied, setCopied] = useState(false);

    useEffect(() => {
        if (!open || !url) return;
        let alive = true;
        QRCode.toDataURL(url, { width: 240, margin: 1 })
            .then((data) => alive && setQr(data))
            .catch(() => alive && setQr(null));
        return () => {
            alive = false;
        };
    }, [open, url]);

    const copy = async () => {
        if (!url) return;
        try {
            await navigator.clipboard.writeText(url);
            setCopied(true);
            window.setTimeout(() => setCopied(false), 1500);
        } catch {
            // No clipboard on a plain-http origin: the address stays selectable.
        }
    };

    return (
        <Dialog open={open} onClose={onClose} title={t('pos.appairer_titre')} description={t('pos.appairer_aide')} size="sm">
            <div className="space-y-3 text-center">
                <div className="mx-auto grid size-[15.5rem] place-items-center rounded-card border border-line bg-white p-2">
                    {qr ? <img src={qr} alt="" className="size-full" style={{ imageRendering: 'pixelated' }} /> : <span className="spinner size-8 text-brand" />}
                </div>
                <p className="font-mono text-xs break-all text-ink-2 select-all" dir="ltr">{url}</p>
                <button className="btn btn-secondary" onClick={copy}>
                    {copied ? <Check className="text-ok" /> : <Copy />}
                    {copied ? t('pos.appairer_copie') : t('pos.appairer_copier')}
                </button>
            </div>
        </Dialog>
    );
}
