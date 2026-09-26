import { useState } from 'react';
import { Delete, Check, X } from 'lucide-react';
import { useT } from '@/auth/session';
import { Dialog } from '@/components/ui/Dialog';

/**
 * + − × ÷ with the usual precedence, plus a postfix %: "200 − 10 %" is 180
 * (the percentage of what came before), "15 %" alone is 0,15. No eval().
 * null when it does not parse.
 */
function evaluate(expr: string): number | null {
    const tokens = expr.replace(/,/g, '.').match(/\d+(?:\.\d+)?|\.\d+|[+\-×÷%]/g);
    if (!tokens || tokens.length === 0) return null;
    const values: number[] = [];
    const ops: string[] = [];
    const apply = () => {
        const b = values.pop(), a = values.pop(), op = ops.pop();
        if (a === undefined || b === undefined || !op) return;
        values.push(op === '+' ? a + b : op === '-' ? a - b : op === '×' ? a * b : b === 0 ? NaN : a / b);
    };
    const prec = (op: string) => (op === '×' || op === '÷' ? 2 : 1);
    let expectNumber = true;
    for (const tk of tokens) {
        if (tk === '%') {
            const v = values.pop();
            if (expectNumber || v === undefined) return null;
            const last = ops[ops.length - 1];
            const base = values[values.length - 1];
            values.push((last === '+' || last === '-') && base !== undefined ? (base * v) / 100 : v / 100);
        } else if (/[+\-×÷]/.test(tk)) {
            if (expectNumber) return null;
            while (ops.length && prec(ops[ops.length - 1]!) >= prec(tk)) apply();
            ops.push(tk);
            expectNumber = true;
        } else {
            if (!expectNumber) return null;
            values.push(Number(tk));
            expectNumber = false;
        }
    }
    if (expectNumber) return null;
    while (ops.length) apply();
    const r = values[0];
    return r === undefined || !Number.isFinite(r) ? null : Math.round(r * 100) / 100;
}

/** Keys as laid out on the pad; the second value is the tone of the key. */
type Tone = 'digit' | 'op' | 'accent';
const KEYS: [string, Tone][] = [
    ['C', 'accent'], ['÷', 'op'], ['%', 'op'], ['⌫', 'accent'],
    ['7', 'digit'], ['8', 'digit'], ['9', 'digit'], ['×', 'op'],
    ['4', 'digit'], ['5', 'digit'], ['6', 'digit'], ['-', 'op'],
    ['1', 'digit'], ['2', 'digit'], ['3', 'digit'], ['+', 'op'],
    [',', 'digit'], ['00', 'digit'], ['0', 'digit'], ['=', 'accent'],
];

const toneClass: Record<Tone, string> = {
    digit: 'bg-surface text-ink shadow-card hover:bg-brand-soft hover:text-brand',
    op: 'bg-line text-ink hover:bg-line-strong',
    accent: 'bg-warm text-white hover:bg-warm/85',
};

/**
 * A big-key calculator for the counter (3 packs at 12,50 + 2 at 7…). The
 * result can be sent straight to "Montant reçu".
 */
export function Calculator({ open, onClose, onUse }: { open: boolean; onClose: () => void; onUse: (value: number) => void }) {
    const t = useT();
    const [expr, setExpr] = useState('');
    const result = evaluate(expr);
    const shown = (n: number) => String(n).replace('.', ',');

    const press = (k: string) => {
        if (k === 'C') return setExpr('');
        if (k === '⌫') return setExpr((e) => e.slice(0, -1));
        if (k === '=') return setExpr(result === null ? expr : shown(result));
        if (k === '.') k = ',';
        setExpr((e) => e + k);
    };

    return (
        <Dialog open={open} onClose={onClose} size="sm">
            <div
                className="space-y-4"
                onKeyDown={(e) => {
                    if (/^[0-9]$/.test(e.key) || ['+', '-', '%'].includes(e.key)) press(e.key);
                    else if (e.key === '*') press('×');
                    else if (e.key === '/') press('÷');
                    else if (e.key === '.' || e.key === ',') press(',');
                    else if (e.key === 'Enter' || e.key === '=') press('=');
                    else if (e.key === 'Backspace') press('⌫');
                    else return;
                    e.preventDefault();
                }}
            >
                <div className="flex items-center justify-between gap-3">
                    <h2 className="text-xl font-extrabold">{t('spa.pos.calculatrice')}</h2>
                    <button type="button" className="grid size-8 place-items-center rounded-full bg-bad text-white hover:bg-bad-ink" onClick={onClose} aria-label={t('spa.ui.fermer')} title={t('spa.ui.fermer')}>
                        <X className="size-4" strokeWidth={3} />
                    </button>
                </div>

                <div className="rounded-xl border border-line bg-surface-2 px-5 py-4 text-end" dir="ltr">
                    <p className="num min-h-5 text-sm break-all text-ink-3">{expr || ' '}</p>
                    <p className="num text-[2.75rem] leading-none font-semibold text-ink-2">{result === null ? (expr ? '…' : '0') : shown(result)}</p>
                </div>

                <div className="grid grid-cols-4 justify-items-center gap-3 rounded-2xl bg-surface-2 p-4" dir="ltr">
                    {KEYS.map(([k, tone]) => (
                        <button
                            key={k}
                            type="button"
                            onClick={() => press(k)}
                            aria-label={k === '⌫' ? t('spa.pos.effacer') : k}
                            data-autofocus={k === 'C' || undefined}
                            className={`num grid size-16 place-items-center rounded-full text-2xl font-semibold transition-[background-color,transform] duration-100 active:scale-95 focus-visible:outline-3 focus-visible:outline-brand/35 ${toneClass[tone]}`}
                        >
                            {k === '⌫' ? <Delete className="size-6" /> : k}
                        </button>
                    ))}
                </div>

                <button type="button" className="btn btn-success min-h-12 w-full rounded-xl" disabled={result === null} onClick={() => { if (result !== null) { onUse(result); setExpr(''); onClose(); } }}>
                    <Check />
                    {t('spa.pos.utiliser_montant')}
                </button>
            </div>
        </Dialog>
    );
}
