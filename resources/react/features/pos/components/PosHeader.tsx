import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { LayoutDashboard, LogOut, Calculator as CalcIcon, Maximize2, Minimize2, RefreshCw, Settings } from 'lucide-react';
import { useSession, useSessionActions, useT } from '@/auth/session';
import { LanguageSwitch } from '@/components/LanguageSwitch';
import { AlertsBell } from '@/components/AlertsBell';
import { ProfileDialog } from '@/components/ProfileDialog';
import { UserAvatar } from '@/components/UserAvatar';
import { formatMoney } from '@/lib/format';
import type { PosJournee, UsbStatus } from '@/types/api';

interface Props {
    usb: UsbStatus | undefined;
    journee: PosJournee | undefined;
    onCalculator: () => void;
    onRefresh: () => void;
    refreshing: boolean;
}

const usbStyles: Record<string, string> = {
    connected: 'bg-ok/20 text-white',
    absent: 'bg-bad/25 text-white',
    erreur: 'bg-bad/25 text-white',
    serie: 'bg-warn/25 text-white',
};

const usbDot: Record<string, string> = {
    connected: 'bg-ok shadow-[0_0_0_3px_rgb(5_150_105/0.3)]',
    absent: 'border-2 border-bad',
    erreur: 'bg-bad',
    serie: 'bg-warn',
};

/** A square tile in the dark bar: one icon, one job. */
const tile = 'grid size-10 flex-none place-items-center rounded-lg border border-white/10 bg-white/[0.06] text-white/85 shadow-[inset_0_1px_0_rgb(255_255_255/0.06)] transition-colors hover:border-white/20 hover:bg-white/[0.12] hover:text-white active:translate-y-px focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/60 disabled:opacity-50';

/**
 * What Windows says about the USB scanner (App\Support\ScannerUsb). Nothing
 * while unknown: a host that cannot be asked must not look like a till with
 * a broken scanner.
 */
function ScannerBadge({ usb }: { usb: UsbStatus | undefined }) {
    if (!usb || usb.state === 'unknown' || !usb.label) return null;

    return (
        <span title={usb.aide ?? undefined} className={`hidden h-10 items-center gap-2 rounded-xl px-3 text-sm font-semibold whitespace-nowrap md:inline-flex ${usbStyles[usb.state] ?? ''}`}>
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
            className={tile}
            title={label}
            aria-label={label}
            onClick={() => void (on ? document.exitFullscreen() : document.documentElement.requestFullscreen()).catch(() => {})}
        >
            {on ? <Minimize2 className="size-5" /> : <Maximize2 className="size-5" />}
        </button>
    );
}

/** Wall clock, so the cashier never has to look for one. */
function Clock() {
    const [now, setNow] = useState(() => new Date());
    useEffect(() => {
        const id = window.setInterval(() => setNow(new Date()), 1000);
        return () => window.clearInterval(id);
    }, []);
    const two = (n: number) => String(n).padStart(2, '0');

    return (
        <span className="num hidden h-10 items-center rounded-xl bg-white/10 px-3 text-[0.95rem] font-bold text-white sm:inline-flex">
            {two(now.getHours())}:{two(now.getMinutes())}:{two(now.getSeconds())}
        </span>
    );
}

export function PosHeader({ usb, journee, onCalculator, onRefresh, refreshing }: Props) {
    const { user, societe, devise } = useSession();
    const { logout } = useSessionActions();
    const t = useT();
    const [profile, setProfile] = useState(false);

    return (
        <header className="flex flex-none flex-wrap items-center gap-x-3 gap-y-2 bg-[linear-gradient(90deg,var(--color-teal),var(--color-teal-deep))] px-3 py-2 text-white">
            <div className="flex min-w-0 items-center gap-2.5">
                <img src="/assets/chinwi-the-doctor.jpeg" alt="" className="h-10 w-auto flex-none rounded-lg bg-white object-contain p-0.5" />
                <div className="hidden min-w-0 leading-tight md:block">
                    <p className="max-w-44 truncate text-[0.95rem] font-extrabold tracking-tight rtl:tracking-normal">{societe ?? t('pos.label')}</p>
                    <p className="truncate text-xs text-teal-ink/80">{t('pos.label')}</p>
                </div>
                <Clock />
            </div>

            <div className="ms-auto flex flex-wrap items-center gap-2">
                {/* Today so far: what was sold and what cash came in. */}
                {journee && (
                    <span className="hidden h-10 items-center gap-2 rounded-xl bg-white/10 px-3 text-sm 2xl:inline-flex" title={t('spa.pos.aujourdhui')}>
                        <span className="font-semibold text-teal-ink/80">{t('spa.pos.aujourdhui')}</span>
                        <span className="num font-extrabold">{formatMoney(journee.total, devise)}</span>
                        <span className="hidden text-xs text-teal-ink/70 2xl:inline">· {t('spa.pos.ventes_jour', { count: journee.ventes })} · <span className="num">{formatMoney(journee.especes, devise)}</span> {t('spa.pos.especes_jour')}</span>
                    </span>
                )}

                <ScannerBadge usb={usb} />

                {/* Back office (dashboard, products, sales...). */}
                <Link className="inline-flex h-10 items-center gap-2 rounded-lg border border-white/15 bg-white px-3.5 text-sm font-bold text-ink shadow-[0_1px_2px_rgb(0_0_0/0.2)] transition-colors hover:bg-white/90 active:translate-y-px focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/60" to="/" title={t('spa.pos.gestion')} aria-label={t('spa.pos.gestion')}>
                    <LayoutDashboard className="size-4" />
                    <span className="hidden xl:inline">{t('spa.pos.gestion')}</span>
                </Link>

                <span className="mx-1 hidden h-7 w-px bg-white/15 lg:block" />

                <button className={`${tile} border-warm/60 bg-warm text-white hover:border-warm hover:bg-warm/90`} onClick={onCalculator} title={t('spa.pos.calculatrice')} aria-label={t('spa.pos.calculatrice')}>
                    <CalcIcon className="size-5" />
                </button>
                <FullscreenButton />
                <button className={tile} onClick={onRefresh} disabled={refreshing} title={t('spa.pos.actualiser')} aria-label={t('spa.pos.actualiser')}>
                    <RefreshCw className={`size-5 ${refreshing ? 'animate-spin' : ''}`} />
                </button>
                <Link className={tile} to="/parametres" title={t('setting.plural')} aria-label={t('setting.plural')}>
                    <Settings className="size-5" />
                </Link>
                <AlertsBell />

                <LanguageSwitch compact />

                <div className="flex h-10 items-center rounded-xl bg-white/10">
                    <button className="flex h-10 items-center gap-2 rounded-s-xl ps-1.5 pe-1 hover:bg-white/15" onClick={() => setProfile(true)} title={t('spa.profil.titre')} aria-label={t('spa.profil.titre')}>
                        <UserAvatar user={user} />
                        <span className="hidden max-w-32 truncate text-sm font-semibold xl:inline">{user?.name}</span>
                    </button>
                    <button className="grid h-10 w-9 place-items-center rounded-e-xl text-white/80 hover:bg-white/15 hover:text-white" onClick={() => void logout()} title={t('spa.auth.deconnexion')} aria-label={t('spa.auth.deconnexion')}>
                        <LogOut className="size-4 rtl:-scale-x-100" />
                    </button>
                </div>
            </div>
            <ProfileDialog open={profile} onClose={() => setProfile(false)} />
        </header>
    );
}
