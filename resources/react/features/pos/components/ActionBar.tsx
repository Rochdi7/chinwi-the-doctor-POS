import { useState } from 'react';
import { CheckCircle2, Clock3, Trash2 } from 'lucide-react';
import { useSession, useT } from '@/auth/session';
import { Dialog } from '@/components/ui/Dialog';
import { formatMoney, formatQty } from '@/lib/format';
import { usePos } from '../store';

interface Props {
    total: number | undefined;
    busy: boolean;
    onEncaisser: () => void;
    onEnregistrer: () => void;
}

/**
 * The three things a cashier does with an order, in a bar of their own
 * under the screen: clear it, park it unpaid, or cash it in (F9).
 */
export function ActionBar({ total, busy, onEncaisser, onEnregistrer }: Props) {
    const t = useT();
    const { devise } = useSession();
    const lines = usePos((s) => s.lines);
    const empty = lines.length === 0;
    const count = lines.reduce((sum, l) => sum + l.quantite, 0);
    const [confirmClear, setConfirmClear] = useState(false);

    return (
        <div className="flex flex-none flex-wrap items-center justify-center gap-2 border-t border-line bg-surface px-3 py-2 short:py-1.5 sm:justify-end">
            <button type="button" className="btn min-h-12 short:min-h-11 rounded-xl bg-bad px-5 text-white hover:bg-bad-ink" disabled={empty || busy} onClick={() => setConfirmClear(true)}>
                <Trash2 />
                {t('pos.vider')}
            </button>

            <button type="button" className="btn min-h-12 short:min-h-11 rounded-xl bg-navy px-5 text-white hover:bg-teal-deep" disabled={empty || busy} onClick={onEnregistrer}>
                <Clock3 />
                {t('pos.sans_paiement')}
            </button>

            <button
                type="button"
                className="btn btn-success min-h-12 short:min-h-11 min-w-56 justify-between rounded-xl px-5 text-lg font-extrabold shadow-[0_8px_18px_-10px_rgb(5_150_105/0.9)]"
                disabled={empty || busy}
                onClick={onEncaisser}
            >
                <span className="flex items-center gap-2">
                    {busy ? <span className="spinner size-5" /> : <CheckCircle2 className="size-6!" />}
                    {busy ? t('spa.pos.traitement') : t('pos.encaisser')}
                    <span className="kbd hidden lg:inline-grid">F9</span>
                </span>
                {!empty && total !== undefined && <span className="num rounded-md bg-black/15 px-2 py-1 text-base">{formatMoney(total, devise)}</span>}
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
                            className="btn btn-primary bg-bad hover:bg-bad-ink"
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
