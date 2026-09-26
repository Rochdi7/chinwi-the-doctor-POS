import { useEffect, useRef, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import { Bell, AlertTriangle, PackageX, Package } from 'lucide-react';
import { useSession, useT } from '@/auth/session';
import { api } from '@/lib/api';
import { formatMoney, formatQty } from '@/lib/format';
import { toast } from '@/components/ui/toast';
import type { Alertes } from '@/types/api';

/**
 * The bell: unpaid sales and empty shelves, counted live. When a count goes
 * up while the app is open (a sale just emptied an article, a sale was
 * left unpaid), a toast says so; the panel lists the first few of each.
 */
export function AlertsBell() {
    const t = useT();
    const { devise } = useSession();
    const [open, setOpen] = useState(false);
    const panel = useRef<HTMLDivElement>(null);
    const seen = useRef<{ impayes: number; ruptures: number } | null>(null);

    const query = useQuery({
        queryKey: ['alertes'],
        queryFn: () => api<Alertes>('/alertes'),
        refetchInterval: 60_000,
        staleTime: 30_000,
    });
    const a = query.data;
    const total = a ? a.impayes.count + a.ruptures.count : 0;

    useEffect(() => {
        if (!a) return;
        if (seen.current) {
            if (a.ruptures.count > seen.current.ruptures) toast.warning(t('spa.alertes.ruptures'), a.ruptures.items[0]?.designation);
            if (a.impayes.count > seen.current.impayes) toast.warning(t('spa.alertes.impayes'), a.impayes.items[0]?.numero);
        }
        seen.current = { impayes: a.impayes.count, ruptures: a.ruptures.count };
    }, [a, t]);

    useEffect(() => {
        if (!open) return;
        const onDown = (e: MouseEvent) => { if (!panel.current?.contains(e.target as Node)) setOpen(false); };
        document.addEventListener('mousedown', onDown);
        return () => document.removeEventListener('mousedown', onDown);
    }, [open]);

    return (
        <div ref={panel} className="relative">
            <button
                className={`btn min-h-10 px-3 ${total > 0 ? 'border-warn/50 bg-warn-soft text-warn-ink' : 'btn-secondary'}`}
                onClick={() => setOpen((o) => !o)}
                aria-expanded={open}
                aria-label={t('spa.alertes.titre')}
                title={t('spa.alertes.titre')}
            >
                <Bell />
                {total > 0 && <span className="num rounded-full bg-bad px-1.5 py-0.5 text-xs font-extrabold text-white">{total}</span>}
            </button>

            {open && (
                <div className="absolute end-0 top-full z-50 mt-2 w-[min(24rem,calc(100vw-2rem))] animate-rise overflow-hidden rounded-card border border-line bg-surface shadow-lift">
                    <p className="border-b border-line px-4 py-2.5 text-sm font-bold">{t('spa.alertes.titre')}</p>
                    {!a ? (
                        <div className="grid h-24 place-items-center"><span className="spinner size-5 text-brand" /></div>
                    ) : total === 0 && a.stock_bas.count === 0 ? (
                        <p className="px-4 py-6 text-center text-sm text-ink-2">{t('spa.alertes.aucune')}</p>
                    ) : (
                        <div className="max-h-[70vh] divide-y divide-line overflow-y-auto">
                            {a.impayes.count > 0 && (
                                <section className="p-3">
                                    <h3 className="mb-1.5 flex items-center gap-2 text-sm font-bold text-bad-ink">
                                        <AlertTriangle className="size-4 text-bad" />
                                        {t('spa.alertes.impayes')} · <span className="num">{a.impayes.count}</span>
                                        <span className="num ms-auto font-extrabold">{formatMoney(a.impayes.total, devise)}</span>
                                    </h3>
                                    <ul className="space-y-1 text-sm">
                                        {a.impayes.items.map((i) => (
                                            <li key={i.id}>
                                                <Link to={`/ventes/${i.id}`} onClick={() => setOpen(false)} className="flex items-center justify-between gap-2 rounded-ctl px-2 py-1.5 hover:bg-surface-2">
                                                    <span className="truncate"><b className="num">{i.numero}</b> · {i.client ?? t('vente.client_passage')}</span>
                                                    <span className="num font-bold text-bad">{formatMoney(i.reste, devise)}</span>
                                                </Link>
                                            </li>
                                        ))}
                                    </ul>
                                    <Link to="/ventes?statut=validee" onClick={() => setOpen(false)} className="mt-1 block px-2 text-xs font-semibold text-brand">{t('spa.alertes.voir_tout')} →</Link>
                                </section>
                            )}
                            {a.ruptures.count > 0 && (
                                <section className="p-3">
                                    <h3 className="mb-1.5 flex items-center gap-2 text-sm font-bold text-bad-ink">
                                        <PackageX className="size-4 text-bad" />
                                        {t('spa.alertes.ruptures')} · <span className="num">{a.ruptures.count}</span>
                                    </h3>
                                    <ul className="space-y-1 text-sm">
                                        {a.ruptures.items.map((i) => (
                                            <li key={i.id} className="flex items-center justify-between gap-2 px-2 py-1.5">
                                                <span className="truncate">{i.designation}</span>
                                                <span className="num rounded-full bg-bad-soft px-2 text-xs font-bold text-bad-ink">{formatQty(i.stock)}</span>
                                            </li>
                                        ))}
                                    </ul>
                                    <Link to="/articles?stock=rupture" onClick={() => setOpen(false)} className="mt-1 block px-2 text-xs font-semibold text-brand">{t('spa.alertes.voir_tout')} →</Link>
                                </section>
                            )}
                            {a.stock_bas.count > 0 && (
                                <section className="p-3">
                                    <h3 className="mb-1.5 flex items-center gap-2 text-sm font-bold text-warn-ink">
                                        <Package className="size-4 text-warn" />
                                        {t('spa.alertes.stock_bas')} · <span className="num">{a.stock_bas.count}</span>
                                    </h3>
                                    <ul className="space-y-1 text-sm">
                                        {a.stock_bas.items.map((i) => (
                                            <li key={i.id} className="flex items-center justify-between gap-2 px-2 py-1.5">
                                                <span className="truncate">{i.designation}</span>
                                                <span className="num rounded-full bg-warn-soft px-2 text-xs font-bold text-warn-ink">{formatQty(i.stock)}</span>
                                            </li>
                                        ))}
                                    </ul>
                                    <Link to="/articles?stock=bas" onClick={() => setOpen(false)} className="mt-1 block px-2 text-xs font-semibold text-brand">{t('spa.alertes.voir_tout')} →</Link>
                                </section>
                            )}
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}
