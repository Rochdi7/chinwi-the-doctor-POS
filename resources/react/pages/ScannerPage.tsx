import { useEffect, useRef, useState } from 'react';
import { useSearchParams, Link } from 'react-router-dom';
import { Camera, Square, ArrowLeft } from 'lucide-react';
import { useT } from '@/auth/session';
import { api, errorMessage } from '@/lib/api';

/** The browser's own decoder (Chrome/Android); iPhone has none. */
interface Detector { detect(source: HTMLVideoElement): Promise<{ rawValue: string }[]> }
declare global {
    interface Window { BarcodeDetector?: new (options: { formats: string[] }) => Detector }
}

const FORMATS = ['ean_13', 'ean_8', 'upc_a', 'upc_e', 'code_128', 'code_39', 'itf', 'qr_code'];

/**
 * The phone as the till's barcode scanner (was App\Filament\Pages\
 * ScannerTelephone). The camera runs here; each code is sent to Laravel,
 * which names the article back and queues it for the till (ScanQueue).
 * The same code is ignored for 1.5 s: the camera sees one barcode 30 times
 * a second, and the cashier means one product.
 */
export default function ScannerPage() {
    const t = useT();
    const [params] = useSearchParams();
    const till = params.get('till') ?? '';
    const video = useRef<HTMLVideoElement>(null);
    const canvas = useRef<HTMLCanvasElement>(null);
    const stopRef = useRef<() => void>(() => {});
    const [running, setRunning] = useState(false);
    const [status, setStatus] = useState<{ text: string; ok: boolean | null }>({ text: t('scanner.pret'), ok: null });
    const [history, setHistory] = useState<string[]>([]);

    useEffect(() => () => stopRef.current(), []);

    const say = (text: string, ok: boolean | null) => {
        setStatus({ text, ok });
        if (ok !== null) navigator.vibrate?.(ok ? 40 : [60, 40, 60]);
    };

    // A short tone: the cashier keeps their eyes on the products.
    const beep = () => {
        try {
            const ctx = new AudioContext();
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.frequency.value = 1750;
            gain.gain.value = 0.15;
            osc.connect(gain).connect(ctx.destination);
            osc.start();
            osc.stop(ctx.currentTime + 0.07);
            window.setTimeout(() => void ctx.close(), 300);
        } catch {
            // No audio: the vibration and the message are enough.
        }
    };

    const start = async () => {
        if (!window.isSecureContext) return say(t('scanner.https'), false);

        let read: () => Promise<string | null>;
        let stream: MediaStream;

        try {
            // The back camera, as close to the product as it can focus.
            stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' }, width: { ideal: 1280 } }, audio: false });
        } catch {
            return say(t('scanner.refus'), false);
        }

        const v = video.current!;
        v.srcObject = stream;
        v.muted = true;
        v.setAttribute('playsinline', '');
        await v.play();

        if (window.BarcodeDetector) {
            const detector = new window.BarcodeDetector({ formats: FORMATS });
            read = async () => (await detector.detect(v))[0]?.rawValue ?? null;
        } else {
            // iPhone: ZXing reads the frames, loaded only when needed.
            try {
                const Z = await import('@zxing/library');
                const reader = new Z.MultiFormatReader();
                const hints = new Map();
                hints.set(Z.DecodeHintType.TRY_HARDER, true);
                hints.set(Z.DecodeHintType.POSSIBLE_FORMATS, [
                    Z.BarcodeFormat.EAN_13, Z.BarcodeFormat.EAN_8, Z.BarcodeFormat.UPC_A, Z.BarcodeFormat.UPC_E,
                    Z.BarcodeFormat.CODE_128, Z.BarcodeFormat.CODE_39, Z.BarcodeFormat.ITF, Z.BarcodeFormat.QR_CODE,
                ]);
                reader.setHints(hints);
                read = async () => {
                    const c = canvas.current!;
                    if (!v.videoWidth) return null;
                    c.width = v.videoWidth;
                    c.height = v.videoHeight;
                    c.getContext('2d', { willReadFrequently: true })!.drawImage(v, 0, 0, c.width, c.height);
                    try {
                        return reader.decode(new Z.BinaryBitmap(new Z.HybridBinarizer(new Z.HTMLCanvasElementLuminanceSource(c)))).getText();
                    } finally {
                        // Without this a second product is never seen.
                        reader.reset();
                    }
                };
            } catch {
                stream.getTracks().forEach((tr) => tr.stop());
                return say(t('scanner.indispo'), false);
            }
        }

        let alive = true;
        let timer = 0;
        let last = '';
        let lastAt = 0;

        const tick = async () => {
            if (!alive) return;
            try {
                const code = (await read())?.trim();
                if (code && !(code === last && Date.now() - lastAt < 1500)) {
                    last = code;
                    lastAt = Date.now();
                    beep();
                    try {
                        const r = await api<{ ok: boolean; message: string }>('/scanner/envoyer', { method: 'POST', body: { code, till } });
                        say(r.message, r.ok);
                        if (r.ok) setHistory((h) => [r.message, ...h].slice(0, 8));
                    } catch (e) {
                        say(errorMessage(e, t), false);
                    }
                }
            } catch {
                // A dropped or unreadable frame is normal; keep looking.
            }
            timer = window.setTimeout(tick, 120);
        };

        stopRef.current = () => {
            alive = false;
            window.clearTimeout(timer);
            stream.getTracks().forEach((tr) => tr.stop());
            setRunning(false);
        };

        setRunning(true);
        say(t('scanner.pret'), null);
        void tick();
    };

    return (
        <div className="mx-auto flex min-h-full max-w-md flex-col p-3">
            <Link to="/pos" className="btn btn-ghost mb-2 min-h-9 self-start px-2 text-sm"><ArrowLeft className="rtl:-scale-x-100" />{t('pos.label')}</Link>
            <section className="panel p-4 text-center">
                <h1 className="text-lg font-extrabold">{t('scanner.titre')}</h1>
                <p className="mt-1 mb-4 text-sm text-ink-2">{t('scanner.aide')}</p>

                <div className="relative grid aspect-[4/3] place-items-center overflow-hidden rounded-card bg-navy">
                    <video ref={video} playsInline muted className="size-full object-cover" />
                    <canvas ref={canvas} hidden />
                    {running && <div className="pointer-events-none absolute inset-x-[10%] inset-y-[22%] rounded-ctl border-3 border-white/90 shadow-[0_0_0_100vmax_rgb(0_0_0/0.25)]" />}
                    {!running && <Camera className="absolute size-12 text-white/40" />}
                </div>

                <p role="status" className={`my-4 min-h-6 text-base font-bold break-words ${status.ok === true ? 'text-ok' : status.ok === false ? 'text-bad' : 'text-ink-2'}`}>
                    {status.text}
                </p>

                {running ? (
                    <button className="btn w-full min-h-14 bg-bad text-lg text-white hover:bg-bad-ink" onClick={() => stopRef.current()}><Square />{t('scanner.arreter')}</button>
                ) : (
                    <button className="btn btn-primary w-full min-h-14 text-lg" onClick={() => void start()}><Camera />{t('scanner.demarrer')}</button>
                )}

                {history.length > 0 && (
                    <div className="mt-4 text-start text-sm">
                        <p className="mb-1 text-xs font-bold tracking-wide text-ink-3 uppercase rtl:normal-case">{t('scanner.derniers')}</p>
                        {history.map((h, i) => <p key={i} className="border-b border-line py-1.5">{h}</p>)}
                    </div>
                )}
            </section>
        </div>
    );
}
