import { useMemo, type RefObject } from 'react';
import { Banknote, CreditCard, ReceiptText, TriangleAlert, UserRound, ScanBarcode, X } from 'lucide-react';
import { useSession, useT } from '@/auth/session';
import { Combobox } from '@/components/ui/Combobox';
import { formatMoney, formatQty } from '@/lib/format';
import { usePos } from '../store';
import { CartLine } from './CartLine';
import type { Apercu, ClientOption, PaymentMode } from '@/types/api';

interface Props {
    apercu: Apercu | undefined;
    apercuStale: boolean;
    clients: ClientOption[];
    amountBox: RefObject<HTMLInputElement | null>;
    /** Set when the panel sits in the bottom sheet: shows a close button. */
    onClose?: () => void;
    className?: string;
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

/**
 * The order being built: customer, lines, totals and the cash handed over.
 * Encaisser / Enregistrer live in the action bar under the screen.
 */
export function CartPanel({ apercu, apercuStale, clients, amountBox, onClose, className = '' }: Props) {
    const t = useT();
    const { devise } = useSession();
    const lines = usePos((s) => s.lines);
    const clientId = usePos((s) => s.clientId);
    const mode = usePos((s) => s.mode);
    const montantRecu = usePos((s) => s.montantRecu);
    const lastAdded = usePos((s) => s.lastAdded);
    const { setClient, setMode, setMontantRecu } = usePos.getState();

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
        <aside className={`panel flex min-h-0 flex-col overflow-hidden rounded-2xl ${className}`}>
            {/* ---- Header ---- */}
            <div className="flex items-center justify-between gap-2 px-4 pt-3 pb-2 short:pt-2">
                <h2 className="flex items-center gap-2 text-lg font-extrabold">
                    <ReceiptText className="size-5 text-brand" />
                    {t('spa.pos.commande')}
                    <span className={`num min-w-7 rounded-full px-2 py-0.5 text-center text-sm font-bold ${empty ? 'bg-surface-2 text-ink-3' : 'bg-brand text-white'}`}>
                        {formatQty(count)}
                    </span>
                </h2>
                {onClose && (
                    <button type="button" className="btn btn-ghost -me-2 min-h-9 px-2" onClick={onClose} aria-label={t('spa.ui.fermer')}>
                        <X />
                    </button>
                )}
            </div>

            {/* ---- Customer & payment mode ---- */}
            <div className="grid grid-cols-[minmax(0,1fr)_auto] gap-2 px-4 pb-3 short:pb-2">
                <Combobox
                    size="sm"
                    icon={<UserRound className="size-4" />}
                    value={clientId === null ? '' : String(clientId)}
                    onChange={(v) => setClient(v === '' ? null : Number(v))}
                    options={clients.map((c) => ({ value: String(c.id), label: c.raison_sociale }))}
                    placeholder={t('vente.client_passage')}
                    clearable
                    aria-label={t('invoice.client')}
                />

                <div role="radiogroup" aria-label={t('spa.pos.paiement')} className="segmented">
                    {modes.map(({ value, icon: Icon, label }) => {
                        const on = mode === value;
                        return (
                            <button
                                key={value}
                                type="button"
                                role="radio"
                                aria-checked={on}
                                onClick={() => setMode(value)}
                                title={label}
                                className={on ? 'text-brand!' : ''}
                            >
                                <Icon className="size-4" />
                                <span className="hidden 2xl:inline">{label}</span>
                            </button>
                        );
                    })}
                </div>
            </div>

            {/* ---- Lines ---- */}
            <div className="grid grid-cols-[auto_minmax(0,1fr)_auto_5rem] gap-x-2 border-y border-line bg-surface-2 px-3 py-1.5 text-[0.7rem] font-bold tracking-wide text-ink-3 uppercase rtl:text-xs rtl:tracking-normal rtl:normal-case sm:grid-cols-[auto_minmax(0,1fr)_auto_6.5rem]">
                <span className="w-8" />
                <span>{t('item.article')}</span>
                <span className="w-26 text-center lg:w-24">{t('item.quantite')}</span>
                <span className="text-end">{t('item.total_ttc')}</span>
            </div>
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
                {(shown?.total_tva ?? 0) > 0 && <div className="flex justify-between gap-4 text-xs text-ink-2">
                    <span>
                        {t('item.total_ht')} <span className="num font-semibold text-ink">{formatMoney(shown?.total_ht ?? 0, devise)}</span>
                    </span>
                    <span>
                        {t('invoice.total_tva')} <span className="num font-semibold text-ink">{formatMoney(shown?.total_tva ?? 0, devise)}</span>
                    </span>
                </div>}
                <div className="flex items-center justify-between gap-3 rounded-xl bg-teal-deep px-4 py-2.5 short:py-1.5 text-white">
                    <span className="text-xs font-bold tracking-wider uppercase opacity-80 rtl:text-sm rtl:tracking-normal rtl:normal-case">{t('pos.total')}</span>
                    <span className="num text-[1.9rem] short:text-[1.6rem] leading-none font-extrabold tracking-tight whitespace-nowrap">
                        {formatMoney(shown?.total_ttc ?? 0, devise)}
                    </span>
                </div>
            </div>

            {/* ---- Cash handed over ---- */}
            <div className="space-y-2 border-t border-line px-4 pt-2.5 pb-3 short:pt-2 short:pb-2.5">
                <div className="short:grid short:grid-cols-[auto_minmax(0,1fr)] short:items-center short:gap-x-3">
                    <label htmlFor="montant-recu" className="mb-1 block short:mb-0 short:max-w-24 text-xs font-bold tracking-wide text-ink-2 uppercase rtl:text-sm rtl:tracking-normal rtl:normal-case">
                        {t('pos.montant_recu')}
                    </label>
                    <div className="flex items-stretch overflow-hidden rounded-xl border border-line-strong bg-surface focus-within:border-brand focus-within:ring-3 focus-within:ring-brand/20" dir="ltr">
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
                                    className="chip num"
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
                    <div className="flex animate-rise items-center justify-between gap-3 rounded-xl border border-ok/30 bg-ok-soft px-4 py-2 text-ok-ink">
                        <span className="text-sm font-bold">{t('pos.monnaie')}</span>
                        <span className="num text-[1.6rem] leading-none font-extrabold">{formatMoney(rendu.monnaie, devise)}</span>
                    </div>
                )}
                {rendu && typed && rendu.reste > 0 && (
                    <div className="flex animate-rise items-center justify-between gap-3 rounded-xl border border-bad/30 bg-bad-soft px-4 py-2 text-bad-ink">
                        <span className="text-sm font-bold">{t('pos.reste')}</span>
                        <span className="num text-[1.6rem] leading-none font-extrabold">{formatMoney(rendu.reste, devise)}</span>
                    </div>
                )}
            </div>
        </aside>
    );
}
