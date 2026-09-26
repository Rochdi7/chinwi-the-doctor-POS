import { useState } from 'react';
import { CheckCircle2, Clock3, ReceiptText, Trash2 } from 'lucide-react';
import { useSession, useT } from '@/auth/session';
import { Dialog } from '@/components/ui/Dialog';
import { formatMoney, formatQty } from '@/lib/format';
import { usePos } from '../store';

interface Props {
    total: number | undefined;
    busy: boolean;
    onEncaisser: () => void;
    onEnregistrer: () => void;
    /**
     * Set on the bar under the screen: on a phone or a portrait tablet it
     * shows a button that opens the cart sheet (count and total on it) and
     * keeps only Encaisser next to it; Vider and Enregistrer wait in the
     * sheet. The same bar without it sits at the bottom of that sheet.
     */
    onOpenCart?: () => void;
}

/**
 * The three things a cashier does with an order, in a bar of their own
 * under the screen: clear it, park it unpaid, or cash it in (F9).
 */
export function ActionBar({ total, busy, onEncaisser, onEnregistrer, onOpenCart }: Props) {
    const t = useT();
    const { devise } = useSession();
    const lines = usePos((s) => s.lines);
    const empty = lines.length === 0;
    const count = lines.reduce((sum, l) => sum + l.quantite, 0);
    const [confirmClear, setConfirmClear] = useState(false);
    const page = onOpenCart !== undefined;

    return (
        <div className="flex flex-none flex-wrap items-center justify-end gap-2 border-t border-line bg-surface/95 px-3 py-2.5 short:py-2 backdrop-blur">
            {page && (
                <button
                    type="button"
                    className={`btn btn-lg short:min-h-11 flex-1 justify-between gap-3 ps-3 pe-3 lg:hidden ${empty ? 'btn-secondary' : 'btn-dark'}`}
                    onClick={onOpenCart}
                    aria-label={`${t('pos.panier')} · ${formatQty(count)}`}
                >
                    <span className="flex min-w-0 items-center gap-2">
                        <ReceiptText />
                        <span className="truncate">{t('pos.panier')}</span>
                        <span className={`num rounded-full px-2 py-0.5 text-xs font-extrabold ${empty ? 'bg-surface-2 text-ink-3 ring-1 ring-line' : 'bg-white/20'}`}>{formatQty(count)}</span>
                    </span>
                    {!empty && total !== undefined && <span className="num text-base font-extrabold">{formatMoney(total, devise)}</span>}
                </button>
            )}

            <button type="button" className={`btn btn-danger-outline btn-lg short:min-h-11 ${page ? 'hidden lg:inline-flex' : 'px-3 sm:px-5'}`} disabled={empty || busy} onClick={() => setConfirmClear(true)}>
                <Trash2 />
                <span className={page ? '' : 'hidden sm:inline'}>{t('pos.vider')}</span>
            </button>

            <button type="button" className={`btn btn-secondary btn-lg short:min-h-11 ${page ? 'hidden lg:inline-flex' : 'min-w-0 flex-1 sm:flex-none'}`} disabled={empty || busy} onClick={onEnregistrer}>
                <Clock3 />
                <span className="truncate">{t('pos.sans_paiement')}</span>
            </button>

            <span className={`mx-1 hidden h-8 w-px bg-line ${page ? 'lg:block' : 'sm:block'}`} />

            <button
                type="button"
                className={`btn btn-success btn-lg short:min-h-11 justify-between gap-4 ps-4 pe-2 text-[1.05rem] font-bold ${page ? 'flex-1 lg:min-w-60 lg:flex-none' : 'basis-full sm:min-w-60 sm:basis-auto'}`}
                disabled={empty || busy}
                onClick={onEncaisser}
            >
                <span className="flex items-center gap-2">
                    {busy ? <span className="spinner size-5" /> : <CheckCircle2 className="size-5!" />}
                    {busy ? t('spa.pos.traitement') : t('pos.encaisser')}
                    <span className="kbd hidden border-white/40 bg-white/10 lg:inline-grid">F9</span>
                </span>
                {!empty && total !== undefined && <span className="num rounded-md bg-black/20 px-2.5 py-1 text-base font-extrabold">{formatMoney(total, devise)}</span>}
            </button>

            <Dialog
                open={confirmClear}
                onClose={() => setConfirmClear(false)}
                size="sm"
                title={t('pos.vider_confirmer')}
                footer={
                    <>
                        <button className="btn btn-secondary" onClick={() => setConfirmClear(false)} data-autofocus>{t('spa.ui.annuler')}</button>
                        <button
                            className="btn btn-danger"
                            onClick={() => {
                                usePos.getState().vider();
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
                    <span className="num font-bold text-ink">{formatQty(count)}</span> · {t('spa.pos.commande')}
                </p>
            </Dialog>
        </div>
    );
}
