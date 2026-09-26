import { memo, useEffect, useState } from 'react';
import { Minus, Plus, Trash2 } from 'lucide-react';
import { useSession, useT } from '@/auth/session';
import { formatMoney, formatQty, parseAmount } from '@/lib/format';
import { usePos, type CartLine as Line } from '../store';
import type { ApercuLine } from '@/types/api';

interface Props {
    line: Line;
    /** This line as Laravel priced it; undefined until the preview answers. */
    priced: ApercuLine | undefined;
    stale: boolean;
    flash: boolean;
}

const stepBtn = 'grid size-8 lg:size-7 flex-none place-items-center rounded-full bg-surface-2 text-ink transition-colors hover:bg-brand-soft hover:text-brand active:bg-brand active:text-white';

/** One order row: remove, name with unit price, − quantity +, line total. */
export const CartLine = memo(function CartLine({ line, priced, stale, flash }: Props) {
    const t = useT();
    const { devise } = useSession();
    const { plus, moins, setQuantite, retirer } = usePos.getState();

    // The quantity box is edited freely, then committed on Enter or blur.
    const [draft, setDraft] = useState(formatQty(line.quantite));
    useEffect(() => setDraft(formatQty(line.quantite)), [line.quantite]);

    const commit = () => {
        const value = parseAmount(draft);
        if (value === null) setDraft(formatQty(line.quantite));
        else if (value !== line.quantite) setQuantite(line.article_id, value);
    };

    // What the customer pays per unit, so "unit × qty" reads as the line total
    // even when the article carries VAT or a discount.
    const unit = priced && priced.quantite > 0 ? priced.total_ttc / priced.quantite : line.prix_vente;

    return (
        <li className={`grid grid-cols-[auto_minmax(0,1fr)_auto_5rem] items-center gap-x-2 border-b border-line px-3 py-2 short:py-1.5 sm:grid-cols-[auto_minmax(0,1fr)_auto_6.5rem] ${flash ? 'animate-flash' : ''}`}>
            {/* Small and away from the stepper, so it is not hit by accident. */}
            <button
                type="button"
                onClick={() => retirer(line.article_id)}
                title={t('pos.retirer')}
                aria-label={t('pos.retirer')}
                className="grid size-8 place-items-center rounded-lg text-ink-3 hover:bg-bad-soft hover:text-bad"
            >
                <Trash2 className="size-4" />
            </button>

            <div className="min-w-0">
                <p className="truncate text-[0.95rem] font-bold">{line.designation}</p>
                <p className="text-xs text-ink-3">
                    <span className="num">{formatMoney(unit, devise)} × {formatQty(line.quantite)}</span>
                </p>
            </div>

            <div className="flex items-center gap-1" dir="ltr">
                <button type="button" onClick={() => moins(line.article_id)} title={t('pos.moins')} aria-label={t('pos.moins')} className={stepBtn}>
                    <Minus className="size-3.5" />
                </button>
                <input
                    className="num h-8 w-8 rounded-md bg-transparent text-center text-sm font-extrabold outline-none focus:bg-brand-soft"
                    inputMode="decimal"
                    value={draft}
                    aria-label={t('item.quantite')}
                    onChange={(e) => setDraft(e.target.value)}
                    onBlur={commit}
                    onKeyDown={(e) => {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            e.currentTarget.blur();
                        }
                    }}
                />
                <button type="button" onClick={() => plus(line.article_id)} title={t('pos.plus')} aria-label={t('pos.plus')} className={stepBtn}>
                    <Plus className="size-3.5" />
                </button>
            </div>

            <p className={`text-end text-[1rem] font-extrabold whitespace-nowrap transition-opacity ${stale || !priced ? 'opacity-50' : ''}`}>
                {priced ? <span className="num">{formatMoney(priced.total_ttc, devise)}</span> : <span className="skeleton inline-block h-5 w-16 rounded" />}
            </p>
        </li>
    );
});
