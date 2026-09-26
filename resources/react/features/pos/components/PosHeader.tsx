import { useEffect, useState, type RefObject } from 'react';
import { Link } from 'react-router-dom';
import { ScanBarcode, LayoutDashboard, LogOut, UserRound, Calculator as CalcIcon, Maximize2, Minimize2 } from 'lucide-react';
import { useSession, useSessionActions, useT } from '@/auth/session';
import { LanguageSwitch } from '@/components/LanguageSwitch';
import { AlertsBell } from '@/components/AlertsBell';
import { formatMoney } from '@/lib/format';
import type { PosJournee, UsbStatus } from '@/types/api';

interface Props {
    scanBox: RefObject<HTMLInputElement | null>;
    scan: string;
    onScanChange: (value: string) => void;
    usb: UsbStatus | undefined;
    journee: PosJournee | undefined;
    onCalculator: () => void;
}

const usbStyles: Record<string, string> = {
    connected: 'bg-ok-soft text-ok-ink',
    absent: 'bg-bad-soft text-bad-ink',
    erreur: 'bg-bad-soft text-bad-ink',
    serie: 'bg-warn-soft text-warn-ink',
};

const usbDot: Record<string, string> = {
    connected: 'bg-ok shadow-[0_0_0_3px_rgb(5_150_105/0.2)]',
    absent: 'border-2 border-bad',
    erreur: 'bg-bad',
    serie: 'bg-warn',
};

/**
 * What Windows says about the USB scanner (App\Support\ScannerUsb). Nothing
 * while unknown: a host that cannot be asked must not look like a till with
 * a broken scanner.
 */
function ScannerBadge({ usb }: { usb: UsbStatus | undefined }) {
    if (!usb || usb.state === 'unknown' || !usb.label) return null;

    return (
        <span title={usb.aide ?? undefined} className={`inline-flex h-10 items-center gap-2 rounded-full px-3 text-sm font-semibold whitespace-nowrap ${usbStyles[usb.state] ?? ''}`}>
            <span className={`size-2.5 flex-none rounded-full ${usbDot[usb.state] ?? ''}`} />
            {usb.label}
        </span>
    );
}

/** The whole till on the whole screen: no browser bar to distract a cashier. */
function FullscreenButton() {
    const t = useT();
    const [on, setOn] = useState(() => document.fullscreenElement !== null);
    useEffect(() => {
        const sync = () => setOn(document.fullscreenElement !== null);
        document.addEventListener('fullscreenchange', sync);
        return () => document.removeEventListener('fullscreenchange', sync);
    }, []);
    if (!document.documentElement.requestFullscreen) return null;

    const label = on ? t('spa.pos.quitter_plein_ecran') : t('spa.pos.plein_ecran');

    return (
        <button
            className="btn btn-secondary min-h-10 px-3"
            title={label}
            aria-label={label}
            onClick={() => void (on ? document.exitFullscreen() : document.documentElement.requestFullscreen()).catch(() => {})}
        >
            {on ? <Minimize2 /> : <Maximize2 />}
        </button>
    );
}

export function PosHeader({ scanBox, scan, onScanChange, usb, journee, onCalculator }: Props) {
    const { user, societe, devise } = useSession();
    const { logout } = useSessionActions();
    const t = useT();

    return (
        <header className="flex flex-wrap items-center gap-x-4 gap-y-2 border-b border-line bg-surface px-3 py-2">
            <div className="flex min-w-0 items-center gap-2.5">
                <img src="/assets/chinwi-the-doctor.jpeg" alt="" className="h-10 w-auto flex-none rounded-md border border-line bg-white object-contain" />
                <div className="hidden min-w-0 leading-tight sm:block">
                    <p className="truncate text-[0.95rem] font-extrabold tracking-tight">{t('pos.label')}</p>
                    <p className="max-w-40 truncate text-xs text-ink-2">{societe}</p>
                </div>
            </div>

            {/* Scans land here by default; a code keyed by hand is sent with Enter. */}
            <label className="relative order-last flex w-full items-center lg:order-none lg:w-auto lg:max-w-xl lg:flex-1">
                <ScanBarcode className="pointer-events-none absolute start-3 size-5 text-brand" />
                <input
                    ref={scanBox}
                    className="field min-h-11 border-2 border-brand bg-brand-soft ps-11 text-lg font-semibold tracking-wide focus:bg-surface"
                    value={scan}
                    onChange={(e) => onScanChange(e.target.value)}
                    placeholder={t('scan.placeholder')}
                    aria-label={t('scan.label')}
                    autoComplete="off"
                    autoFocus
                    enterKeyHint="done"
                    spellCheck={false}
                />
            </label>

            <div className="ms-auto flex flex-wrap items-center gap-2">
                {/* Today so far: what was sold and what cash came in. */}
                {journee && (
                    <span className="hidden h-10 items-center gap-2 rounded-full bg-surface-2 px-3 text-sm md:inline-flex" title={t('spa.pos.aujourdhui')}>
                        <span className="font-semibold text-ink-2">{t('spa.pos.aujourdhui')}</span>
                        <span className="num font-extrabold">{formatMoney(journee.total, devise)}</span>
                        <span className="num hidden text-xs text-ink-3 xl:inline">· {t('spa.pos.ventes_jour', { count: journee.ventes })} · {formatMoney(journee.especes, devise)} {t('spa.pos.especes_jour')}</span>
                    </span>
                )}

                <ScannerBadge usb={usb} />

                <button className="btn btn-secondary min-h-10 px-3" onClick={onCalculator} title={t('spa.pos.calculatrice')} aria-label={t('spa.pos.calculatrice')}>
                    <CalcIcon />
                </button>
                <FullscreenButton />
                <AlertsBell />

                <LanguageSwitch compact />

                {/* Back office (dashboard, articles, ventes...). */}
                <Link className="btn btn-ghost min-h-10 px-3" to="/" title={t('spa.pos.gestion')}>
                    <LayoutDashboard />
                    <span className="hidden 2xl:inline">{t('spa.pos.gestion')}</span>
                </Link>

                <div className="flex h-10 items-center gap-1 rounded-full border border-line ps-3">
                    <UserRound className="size-4 text-ink-3" />
                    <span className="max-w-32 truncate text-sm font-semibold">{user?.name}</span>
                    <button className="btn btn-ghost min-h-9 rounded-full px-2.5" onClick={() => void logout()} title={t('spa.auth.deconnexion')} aria-label={t('spa.auth.deconnexion')}>
                        <LogOut className="rtl:-scale-x-100" />
                    </button>
                </div>
            </div>
        </header>
    );
}
