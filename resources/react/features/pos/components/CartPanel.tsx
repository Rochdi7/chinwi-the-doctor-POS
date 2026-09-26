import { useMemo, useState, type RefObject } from 'react';
import { Banknote, CheckCircle2, Clock3, CreditCard, ShoppingBag, Trash2, TriangleAlert, UserRound, ScanBarcode } from 'lucide-react';
import { useSession, useT } from '@/auth/session';
import { Dialog } from '@/components/ui/Dialog';
import { formatMoney, formatQty } from '@/lib/format';
import { usePos } from '../store';
import { CartLine } from './CartLine';
import type { Apercu, ClientOption, PaymentMode } from '@/types/api';

interface Props {
    apercu: Apercu | undefined;
    apercuStale: boolean;
    clients: ClientOption[];
    busy: boolean;
    onEncaisser: () => void;
    onEnregistrer: () => void;
    amountBox: RefObject<HTMLInputElement | null>;
}

/**
 * Round sums a customer is likely to hand over, for one-tap entry. Only a
 * shortcut to type the amount: change is still computed by Laravel.
 */
function quickAmounts(total: number): number[] {
    if (total <= 0) return [];
    const out = new Set<number>([total]);
    for (const step of [10, 50, 100, 200]) {
        const next = Math.ceil(total / step) * step;
        if (next > total) out.add(next);
        if (out.size >= 4) break;
    }
    return [...out].slice(0, 4);
}

export function CartPanel({ apercu, apercuStale, clients, busy, onEncaisser, onEnregistrer, amountBox }: Props) {
    const t = useT();
    const { devise } = useSession();
    const lines = usePos((s) => s.lines);
    const clientId = usePos((s) => s.clientId);
    const mode = usePos((s) => s.mode);
    const montantRecu = usePos((s) => s.montantRecu);
    const lastAdded = usePos((s) => s.lastAdded);
    const { setClient, setMode, setMontantRecu, vider } = usePos.getState();
    const [confirmClear, setConfirmClear] = useState(false);

    const empty = lines.length === 0;
    // With an empty cart the last preview is kept by the query cache; never show it.
    const shown = empty ? undefined : apercu;
    const priced = useMemo(() => new Map((shown?.lignes ?? []).map((l) => [l.article_id, l])), [shown]);
    const count = lines.reduce((sum, l) => sum + l.quantite, 0);
    const rendu = shown?.rendu;
    const typed = montantRecu.trim() !== '';

    const modes: { value: PaymentMode; icon: typeof Banknote; label: string }[] = [
        { value: 'especes', icon: Banknote, label: t('mode.especes') },
        { value: 'tpe', icon: CreditCard, label: t('mode.tpe') },
    ];

    return (
        <aside className="panel flex min-h-0 flex-col overflow-hidden">
            {/* ---- Header ---- */}
            <div className="flex items-center justify-between gap-2 border-b border-line px-4 py-2.5 short:py-1.5">
                <h2 className="flex items-center gap-2 text-base font-extrabold">
                    <ShoppingBag className="size-5 text-brand" />
                    {t('pos.panier')}
                    <span className={`num min-w-7 rounded-full px-2 py-0.5 text-center text-sm font-bold ${empty ? 'bg-surface-2 text-ink-3' : 'bg-brand text-white'}`}>
                        {formatQty(count)}
                    </span>
                </h2>
                <button type="button" className="btn btn-danger-ghost min-h-9 px-2.5 text-sm" disabled={empty || busy} onClick={() => setConfirmClear(true)}>
                    <Trash2 className="size-4" />
                    {t('pos.vider')}
                </button>
            </div>

            {/* ---- Lines ---- */}
            <div className="min-h-24 flex-1 overflow-y-auto">
                {empty ? (
                    <div className="flex h-full min-h-40 flex-col items-center justify-center gap-2 px-6 py-8 text-center">
                        <span className="grid size-14 place-items-center rounded-full border border-dashed border-line-strong bg-surface-2">
                            <ScanBarcode className="size-7 text-ink-3" />
                        </span>
                        <p className="font-bold">{t('spa.pos.panier_vide_titre')}</p>
                        <p className="text-sm text-ink-2">{t('pos.panier_vide')}</p>
                    </div>
                ) : (
                    <ul>
                        {lines.map((line) => (
                            <CartLine
                                key={line.article_id}
                                line={line}
                                priced={priced.get(line.article_id)}
                                stale={apercuStale}
                                flash={lastAdded?.id === line.article_id && Date.now() - lastAdded.at < 600}
                            />
                        ))}
                    </ul>
                )}
                {shown && shown.avertissements.length > 0 && (
                    <ul className="space-y-1 px-4 py-2">
                        {shown.avertissements.map((w) => (
                            <li key={w.article_id} className="flex items-start gap-1.5 text-xs font-medium text-warn-ink">
                                <TriangleAlert className="mt-px size-3.5 flex-none text-warn" />
                                {w.message}
                            </li>
                        ))}
                    </ul>
                )}
            </div>

            {/* ---- Totals (from Laravel) ---- */}
            <div className={`space-y-1.5 border-t border-line bg-surface-2 px-4 py-2.5 short:py-1.5 transition-opacity ${apercuStale && !empty ? 'opacity-70' : ''}`}>
                <div className="flex justify-between gap-4 text-xs text-ink-2">
                    <span>
                        {t('item.total_ht')} <span className="num font-semibold text-ink">{formatMoney(shown?.total_ht ?? 0, devise)}</span>
                    </span>
                    <span>
                        {t('invoice.total_tva')} <span className="num font-semibold text-ink">{formatMoney(shown?.total_tva ?? 0, devise)}</span>
                    </span>
                </div>
                <div className="flex items-center justify-between gap-3 rounded-ctl bg-navy px-4 py-2.5 short:py-1.5 text-white">
                    <span className="text-xs font-bold tracking-wider uppercase opacity-80 rtl:text-sm rtl:tracking-normal rtl:normal-case">{t('pos.total')}</span>
                    <span className="num text-[1.9rem] short:text-[1.6rem] leading-none font-extrabold tracking-tight whitespace-nowrap">
                        {formatMoney(shown?.total_ttc ?? 0, devise)}
                    </span>
                </div>
            </div>

            {/* ---- Payment ---- */}
            <div className="space-y-2.5 border-t border-line px-4 pt-3 pb-4 short:space-y-2 short:pt-2 short:pb-3">
                <div className="grid grid-cols-[minmax(0,1fr)_auto] gap-2">
                    <label className="relative flex items-center">
                        <UserRound className="pointer-events-none absolute start-3 size-4 text-ink-3" />
                        <select
                            className="field ps-9 text-sm"
                            value={clientId ?? ''}
                            onChange={(e) => setClient(e.target.value === '' ? null : Number(e.target.value))}
                            aria-label={t('invoice.client')}
                        >
                            <option value="">{t('vente.client_passage')}</option>
                            {clients.map((c) => <option key={c.id} value={c.id}>{c.raison_sociale}</option>)}
                        </select>
                    </label>

                    <div role="radiogroup" aria-label={t('spa.pos.paiement')} className="flex gap-1 rounded-ctl bg-surface-2 p-1 ring-1 ring-line">
                        {modes.map(({ value, icon: Icon, label }) => {
                            const on = mode === value;
                            return (
                                <button
                                    key={value}
                                    type="button"
                                    role="radio"
                                    aria-checked={on}
                                    onClick={() => setMode(value)}
                                    className={`flex min-h-9 items-center gap-1.5 rounded-md px-3 text-sm font-bold transition-colors ${on ? 'bg-surface text-brand shadow-card ring-2 ring-brand' : 'text-ink-2 hover:text-ink'}`}
                                >
                                    <Icon className="size-4" />
                                    {label}
                                </button>
                            );
                        })}
                    </div>
                </div>

                <div className="short:grid short:grid-cols-[auto_minmax(0,1fr)] short:items-center short:gap-x-3">
                    <label htmlFor="montant-recu" className="mb-1 block short:mb-0 short:max-w-24 text-xs font-bold tracking-wide text-ink-2 uppercase rtl:text-sm rtl:tracking-normal rtl:normal-case">
                        {t('pos.montant_recu')} <span className="kbd ms-1 align-middle text-ink-3 normal-case">F4</span>
                    </label>
                    <div className="flex items-stretch overflow-hidden rounded-ctl border border-line-strong bg-surface focus-within:border-brand focus-within:ring-3 focus-within:ring-brand/20">
                        <input
                            id="montant-recu"
                            ref={amountBox}
                            className="num min-w-0 flex-1 bg-transparent px-3 text-2xl short:text-xl short:[--amount-h:2.6rem] font-extrabold outline-none placeholder:font-semibold placeholder:text-ink-3"
                            style={{ height: 'var(--amount-h, 3rem)' }}
                            inputMode="decimal"
                            autoComplete="off"
                            value={montantRecu}
                            placeholder={shown ? formatMoney(shown.total_ttc, '').trim() : ''}
                            onChange={(e) => setMontantRecu(e.target.value)}
                            disabled={empty}
                        />
                        <span className="grid place-items-center border-s border-line bg-surface-2 px-3 font-bold text-ink-2">{devise}</span>
                    </div>
                    {!empty && shown && !typed && (
                        <div className="mt-1.5 flex flex-wrap gap-1.5 short:col-span-2">
                            {quickAmounts(shown.total_ttc).map((amount) => (
                                <button
                                    key={amount}
                                    type="button"
                                    className="num rounded-full border border-line-strong bg-surface px-2.5 py-1 text-xs font-bold text-ink-2 hover:border-brand hover:text-brand"
                                    onClick={() => setMontantRecu(String(amount))}
                                >
                                    {formatMoney(amount, devise)}
                                </button>
                            ))}
                        </div>
                    )}
                    {!typed && <p className="mt-1 text-xs text-ink-3 short:hidden">{t('pos.montant_recu_aide')}</p>}
                </div>

                {/* Change to hand back, or what is still owed: Laravel's figures. */}
                {rendu && typed && rendu.monnaie > 0 && (
                    <div className="flex animate-rise items-center justify-between gap-3 rounded-ctl border border-ok/30 bg-ok-soft px-4 py-2 text-ok-ink">
                        <span className="text-sm font-bold">{t('pos.monnaie')}</span>
                        <span className="num text-[1.6rem] leading-none font-extrabold">{formatMoney(rendu.monnaie, devise)}</span>
                    </div>
                )}
                {rendu && typed && rendu.reste > 0 && (
                    <div className="flex animate-rise items-center justify-between gap-3 rounded-ctl border border-bad/30 bg-bad-soft px-4 py-2 text-bad-ink">
                        <span className="text-sm font-bold">{t('pos.reste')}</span>
                        <span className="num text-[1.6rem] leading-none font-extrabold">{formatMoney(rendu.reste, devise)}</span>
                    </div>
                )}

                <button
                    type="button"
                    className="btn btn-success min-h-[3.5rem] short:min-h-12 w-full justify-between px-4 text-lg font-extrabold shadow-[0_8px_18px_-10px_rgb(5_150_105/0.9)]"
                    disabled={empty || busy}
                    onClick={onEncaisser}
                >
                    <span className="flex items-center gap-2">
                        {busy ? <span className="spinner size-5" /> : <CheckCircle2 className="size-6!" />}
                        {busy ? t('spa.pos.traitement') : t('pos.encaisser')}
                        {!busy && <span className="kbd border-white/60 text-white">F9</span>}
                    </span>
                    {!empty && shown && <span className="num rounded-md bg-black/15 px-2 py-1 text-base">{formatMoney(shown.total_ttc, devise)}</span>}
                </button>

                <button type="button" className="btn btn-secondary w-full short:min-h-10" disabled={empty || busy} onClick={onEnregistrer}>
                    <Clock3 />
                    {t('pos.sans_paiement')}
                </button>
            </div>

            <Dialog
                open={confirmClear}
                onClose={() => setConfirmClear(false)}
                size="sm"
                title={`${t('pos.vider')} ?`}
                footer={
                    <>
                        <button className="btn btn-secondary" onClick={() => setConfirmClear(false)} data-autofocus>✕</button>
                        <button
                            className="btn btn-primary bg-bad hover:bg-bad-ink"
                            onClick={() => {
                                vider();
                                setConfirmClear(false);
                            }}
                        >
                            <Trash2 />
                            {t('pos.vider')}
                        </button>
                    </>
                }
            >
                <p className="text-sm text-ink-2">
                    <span className="num font-bold text-ink">{formatQty(count)}</span> · {t('pos.panier')}
                </p>
            </Dialog>
        </aside>
    );
}
