import { memo, useEffect, useState } from 'react';
import { Minus, Plus, X } from 'lucide-react';
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

/** One cart line: name, unit price × quantity, stepper, total, remove. */
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
        // Fixed total column: steppers, × and totals line up from row to row.
        <li className={`grid grid-cols-[minmax(0,1fr)_8.5rem] items-center gap-x-3 gap-y-2 border-b border-line px-4 py-2.5 short:py-1.5 ${flash ? 'animate-flash' : ''}`}>
            <div className="min-w-0">
                <p className="truncate font-bold">{line.designation}</p>
                <p className="text-xs text-ink-2">
                    <span className="num">{formatMoney(unit, devise)} × {formatQty(line.quantite)}</span>
                </p>
            </div>

            {/* Small and away from the stepper, so it is not hit by accident. */}
            <button
                type="button"
                onClick={() => retirer(line.article_id)}
                title={t('pos.retirer')}
                aria-label={t('pos.retirer')}
                className="grid size-8 place-items-center self-start justify-self-end rounded-ctl text-ink-3 hover:bg-bad-soft hover:text-bad"
            >
                <X className="size-4" />
            </button>

            <div className="inline-flex w-fit items-center justify-self-start overflow-hidden rounded-ctl border border-line-strong bg-surface" dir="ltr">
                <button type="button" onClick={() => moins(line.article_id)} title={t('pos.moins')} aria-label={t('pos.moins')} className="grid h-10 w-11 place-items-center bg-surface-2 text-ink hover:bg-brand-soft hover:text-brand active:bg-brand active:text-white short:h-9">
                    <Minus className="size-4" />
                </button>
                <input
                    className="num h-10 w-14 short:h-9 border-x border-line bg-transparent text-center font-extrabold outline-none focus:bg-brand-soft"
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
                <button type="button" onClick={() => plus(line.article_id)} title={t('pos.plus')} aria-label={t('pos.plus')} className="grid h-10 w-11 place-items-center bg-surface-2 text-ink hover:bg-brand-soft hover:text-brand active:bg-brand active:text-white short:h-9">
                    <Plus className="size-4" />
                </button>
            </div>

            <p className={`text-end text-[1.05rem] font-extrabold whitespace-nowrap transition-opacity ${stale || !priced ? 'opacity-50' : ''}`}>
                {priced ? <span className="num">{formatMoney(priced.total_ttc, devise)}</span> : <span className="skeleton inline-block h-5 w-20 rounded" />}
            </p>
        </li>
    );
});
