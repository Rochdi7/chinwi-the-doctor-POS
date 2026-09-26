import { useState } from 'react';
import { Delete, Check } from 'lucide-react';
import { useT } from '@/auth/session';
import { Dialog } from '@/components/ui/Dialog';

/** + − × ÷ with the usual precedence, no eval(). null when it does not parse. */
function evaluate(expr: string): number | null {
    const tokens = expr.replace(/,/g, '.').match(/\d+(?:\.\d+)?|[+\-×÷]/g);
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
        if (/[+\-×÷]/.test(tk)) {
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

/**
 * A big-key calculator for the counter (3 packs at 12,50 + 2 at 7…). The
 * result can be sent straight to "Montant reçu".
 */
export function Calculator({ open, onClose, onUse }: { open: boolean; onClose: () => void; onUse: (value: number) => void }) {
    const t = useT();
    const [expr, setExpr] = useState('');
    const result = evaluate(expr);

    const press = (k: string) => {
        if (k === 'C') return setExpr('');
        if (k === '⌫') return setExpr((e) => e.slice(0, -1));
        if (k === '=') return setExpr(result === null ? expr : String(result).replace('.', ','));
        setExpr((e) => e + k);
    };

    const keys = ['7', '8', '9', '÷', '4', '5', '6', '×', '1', '2', '3', '-', '0', ',', '=', '+'];

    return (
        <Dialog open={open} onClose={onClose} size="sm" title={t('spa.pos.calculatrice')}>
            <div
                className="space-y-3"
                onKeyDown={(e) => {
                    if (/^[0-9]$/.test(e.key) || ['+', '-'].includes(e.key)) press(e.key);
                    else if (e.key === '*') press('×');
                    else if (e.key === '/') press('÷');
                    else if (e.key === '.' || e.key === ',') press(',');
                    else if (e.key === 'Enter') press('=');
                    else if (e.key === 'Backspace') press('⌫');
                    else return;
                    e.preventDefault();
                }}
            >
                <div className="rounded-ctl border border-line bg-surface-2 px-4 py-3 text-end" dir="ltr">
                    <p className="num min-h-6 text-lg break-all text-ink-2">{expr || '0'}</p>
                    <p className="num text-3xl font-extrabold">{result === null ? (expr ? '…' : '0') : String(result).replace('.', ',')}</p>
                </div>
                <div className="grid grid-cols-4 gap-2" dir="ltr">
                    <button type="button" className="btn btn-secondary col-span-3 min-h-12 text-bad" onClick={() => press('C')} data-autofocus>{t('spa.pos.effacer')}</button>
                    <button type="button" className="btn btn-secondary min-h-12" onClick={() => press('⌫')} aria-label={t('spa.pos.effacer')}><Delete /></button>
                    {keys.map((k) => (
                        <button key={k} type="button" onClick={() => press(k)} className={`btn min-h-14 text-xl font-bold ${/[÷×\-+=]/.test(k) ? 'btn-secondary text-brand' : 'btn-secondary'}`}>{k}</button>
                    ))}
                </div>
                <button type="button" className="btn btn-success w-full min-h-12" disabled={result === null} onClick={() => { if (result !== null) { onUse(result); setExpr(''); onClose(); } }}>
                    <Check />
                    {t('spa.pos.utiliser_montant')}
                </button>
            </div>
        </