import { useRef } from 'react';
import { CheckCircle2, FileText, Printer, ArrowRight, Download } from 'lucide-react';
import { useSession, useT } from '@/auth/session';
import { Dialog } from '@/components/ui/Dialog';
import { formatMoney } from '@/lib/format';
import type { VenteResult } from '@/types/api';

const statutStyle: Record<string, string> = {
    payee: 'bg-ok-soft text-ok-ink',
    partielle: 'bg-warn-soft text-warn-ink',
    validee: 'bg-brand-soft text-brand',
};

/**
 * After Encaisser: what was saved, the change to hand back in big figures,
 * and the receipt one tap away. Enter or a new scan starts the next sale.
 */
export function SaleDoneDialog({ result, onClose }: { result: VenteResult | null; onClose: () => void }) {
    const t = useT();
    const { devise } = useSession();

    const next = useRef<HTMLButtonElement>(null);

    // After printing, Enter must still mean "next customer", not "print again".
    const open = (url: string) => {
        window.open(url, '_blank', 'noopener');
        next.current?.focus();
    };

    return (
        <Dialog open={result !== null} onClose={onClose} size="sm">
            {result && (
                <div className="space-y-4 text-center">
                    <CheckCircle2 className="mx-auto size-14 text-ok" strokeWidth={1.75} />
                    <div>
                        <p className="text-lg font-extrabold">{result.message}</p>
                        <p className="mt-1 flex items-center justify-center gap-2 text-sm text-ink-2">
                            <span className="num font-semibold text-ink">{formatMoney(result.invoice.total_ttc, devise)}</span>
                            <span className={`rounded-full px-2 py-0.5 text-xs font-bold ${statutStyle[result.invoice.statut] ?? ''}`}>
                                {t(`statut.${result.invoice.statut}`)}
                            </span>
                        </p>
                    </div>

                    {result.monnaie > 0 && (
                        <div className="rounded-card border border-ok/30 bg-ok-soft px-4 py-3 text-ok-ink">
                            <p className="text-sm font-bold">{t('pos.monnaie')}</p>
                            <p className="num text-4xl font-extrabold">{formatMoney(result.monnaie, devise)}</p>
                        </div>
                    )}

                    <div className="grid gap-2">
                        {result.payment && (
                            <button className="btn btn-primary w-full" onClick={() => open(result.payment!.pdf_url)}>
                                <Printer />
                                {t('receipt.print')}
                            </button>
                        )}
                        {/* Saved as a file rather than opened: the till may have no PDF viewer. */}
                        <a className="btn btn-secondary w-full" href={result.invoice.recu_url} download onClick={() => next.current?.focus()}>
                            <Download />
                            {t('receipt.telecharger')}
                        </a>
                        <button className="btn btn-secondary w-full" onClick={() => open(result.invoice.pdf_url)}>
                            <FileText />
                            {t('invoice.print')} · {t('invoice.label')}
                        </button>
                        <button ref={next} className="btn btn-ghost w-full" onClick={onClose} data-autofocus>
                            {t('pos.nouvelle_vente')}
                            <ArrowRight className="rtl:-scale-x-100" />
                            <span className="kbd">↵</span>
                        </button>
                    </div>
                </div>
            )}
        </Dialog>
    );
}
