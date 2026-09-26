import { useState, type ReactNode } from 'react';
import { Search, X, Trash2 } from 'lucide-react';
import { useSession, useT } from '@/auth/session';
import { formatMoney } from '@/lib/format';
import { Dialog } from './Dialog';
import type { InvoiceStatut, PaymentMode } from '@/types/api';

/** Title row of a screen: heading, subtitle, actions on the end side. */
export function PageHeader({ title, subtitle, actions }: { title: ReactNode; subtitle?: ReactNode; actions?: ReactNode }) {
    return (
        <div className="mb-4 flex flex-wrap items-end justify-between gap-3">
            <div className="min-w-0">
                <h1 className="text-2xl font-extrabold tracking-tight">{title}</h1>
                {subtitle && <p className="mt-0.5 text-sm text-ink-2">{subtitle}</p>}
            </div>
            {actions && <div className="flex flex-wrap items-center gap-2">{actions}</div>}
        </div>
    );
}

/** The filter row above a list: search first, then filters. */
export function Toolbar({ children }: { children: ReactNode }) {
    return <div className="flex flex-wrap items-center gap-2 border-b border-line p-3">{children}</div>;
}

export function SearchBox({ value, onChange, placeholder }: { value: string; onChange: (v: string) => void; placeholder?: string }) {
    const t = useT();

    return (
        <label className="relative flex min-w-56 flex-1 items-center">
            <Search className="pointer-events-none absolute start-3 size-4 text-ink-3" />
            <input
                className="field min-h-10 bg-surface-2 ps-9 pe-9 focus:bg-surface"
                value={value}
                onChange={(e) => onChange(e.target.value)}
                placeholder={placeholder ?? t('spa.ui.rechercher')}
                aria-label={placeholder ?? t('spa.ui.rechercher')}
            />
            {value && (
                <button type="button" className="absolute end-2 grid size-7 place-items-center rounded-full text-ink-3 hover:bg-line hover:text-ink" onClick={() => onChange('')} aria-label="×">
                    <X className="size-4" />
                </button>
            )}
        </label>
    );
}

/** A compact select used as a list filter. */
export function FilterSelect({ value, onChange, options, label }: { value: string; onChange: (v: string) => void; options: { value: string; label: string }[]; label: string }) {
    return (
        <select className="field min-h-10 w-auto text-sm font-semibold" value={value} onChange={(e) => onChange(e.target.value)} aria-label={label}>
            {options.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
        </select>
    );
}

type Tone = 'neutral' | 'ok' | 'warn' | 'bad' | 'brand';

const tones: Record<Tone, string> = {
    neutral: 'bg-surface-2 text-ink-2 ring-1 ring-line',
    ok: 'bg-ok-soft text-ok-ink',
    warn: 'bg-warn-soft text-warn-ink',
    bad: 'bg-bad-soft text-bad-ink',
    brand: 'bg-brand-soft text-brand',
};

export function Badge({ tone = 'neutral', children }: { tone?: Tone; children: ReactNode }) {
    return <span className={`inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-bold whitespace-nowrap ${tones[tone]}`}>{children}</span>;
}

const statutTone: Record<InvoiceStatut, Tone> = { payee: 'ok', partielle: 'warn', validee: 'brand' };

/** Invoice status: colour plus its word, never colour alone. */
export function StatutBadge({ statut }: { statut: InvoiceStatut }) {
    const t = useT();
    return <Badge tone={statutTone[statut]}>{t(`statut.${statut}`)}</Badge>;
}

export function ModeBadge({ mode }: { mode: PaymentMode }) {
    const t = useT();
    return <Badge tone={mode === 'especes' ? 'ok' : 'neutral'}>{t(`mode.${mode}`)}</Badge>;
}

/** Money in the configured currency, Latin digits, isolated in RTL. */
export function Money({ value, className = '' }: { value: number | null | undefined; className?: string }) {
    const { devise } = useSession();
    return <span className={`num whitespace-nowrap ${className}`}>{value === null || value === undefined ? '—' : formatMoney(value, devise)}</span>;
}

export function useMoney() {
    const { devise } = useSession();
    return (value: number) => formatMoney(value, devise);
}

/** "2026-09-26" -> "26/09/2026", as the panel showed dates. */
export function DateText({ value }: { value: string | null | undefined }) {
    if (!value) return <span>—</span>;
    const [y, m, d] = value.slice(0, 10).split('-');
    return <span className="num whitespace-nowrap">{`${d}/${m}/${y}`}{value.length > 10 ? ` ${value.slice(11)}` : ''}</span>;
}

/** Delete with a confirmation step; the action runs only after "Supprimer". */
export function DeleteButton({ onConfirm, label, compact = false }: { onConfirm: () => Promise<unknown>; label?: ReactNode; compact?: boolean }) {
    const t = useT();
    const [open, setOpen] = useState(false);
    const [busy, setBusy] = useState(false);

    return (
        <>
            <button type="button" className={`btn btn-danger-ghost ${compact ? 'min-h-9 px-2.5' : ''}`} onClick={(e) => { e.stopPropagation(); setOpen(true); }} title={t('spa.ui.supprimer')}>
                <Trash2 />
                {!compact && (label ?? t('spa.ui.supprimer'))}
            </button>
            <Dialog
                open={open}
                onClose={() => setOpen(false)}
                size="sm"
                title={t('spa.ui.confirmer_suppression')}
                description={t('spa.ui.suppression_aide')}
                footer={
                    <>
                        <button className="btn btn-secondary" onClick={() => setOpen(false)} data-autofocus>{t('spa.ui.annuler')}</button>
                        <button
                            className="btn btn-primary bg-bad hover:bg-bad-ink"
                            disabled={busy}
                            onClick={async () => {
                                setBusy(true);
                                try {
                                    await onConfirm();
                                    setOpen(false);
                                } finally {
                                    setBusy(false);
                                }
                            }}
                        >
                            {busy ? <span className="spinner size-4" /> : <Trash2 />}
                            {t('spa.ui.supprimer')}
                        </button>
                    </>
                }
            >
                {label && <p className="text-sm font-semibold">{label}</p>}
            </Dialog>
        </>
    );
}

/** A card-like section inside a screen. */
export function Section({ title, actions, children, className = '' }: { title?: ReactNode; actions?: ReactNode; children: ReactNode; className?: string }) {
    return (
        <section className={`panel ${className}`}>
            {(title || actions) && (
                <div className="flex items-center justify-between gap-3 border-b border-line px-4 py-3">
                    {title && <h2 className="text-base font-bold">{title}</h2>}
                    {actions}
                </div>
            )}
            {children}
        </section>
    );
}
