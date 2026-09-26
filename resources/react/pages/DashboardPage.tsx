import { useState } from 'react';
import { useQuery, keepPreviousData } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import { BarChart3, CheckCircle2, AlertTriangle, Wallet, ShoppingCart } from 'lucide-react';
import { useT } from '@/auth/session';
import { api } from '@/lib/api';
import { Dialog } from '@/components/ui/Dialog';
import { DataTable, type Column } from '@/components/ui/table';
import { DateText, Money, ModeBadge, PageHeader, StatutBadge, useMoney } from '@/components/ui/misc';
import { BarList, ChartCard, LineChart, Legend, SERIES, ShareBar } from '@/components/charts';
import type { Dashboard, DashboardDetail, DashboardKey, InvoiceStatut, PaymentMode } from '@/types/api';

type Preset = 'tout' | 'aujourdhui' | 'ce_mois' | 'cette_annee' | 'custom';

const iso = (d: Date) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;

function rangeFor(preset: Preset): { du: string; au: string } {
    const now = new Date();
    switch (preset) {
        case 'aujourdhui':
            return { du: iso(now), au: iso(now) };
        case 'ce_mois':
            return { du: iso(new Date(now.getFullYear(), now.getMonth(), 1)), au: iso(now) };
        case 'cette_annee':
            return { du: iso(new Date(now.getFullYear(), 0, 1)), au: iso(now) };
        default:
            return { du: '', au: '' };
    }
}

const statutColor: Record<InvoiceStatut, string> = { payee: '#059669', partielle: '#d97706', validee: '#1d4ed8' };

export default function DashboardPage() {
    const t = useT();
    const money = useMoney();
    const [preset, setPreset] = useState<Preset>('tout');
    const [range, setRange] = useState({ du: '', au: '' });
    const [detail, setDetail] = useState<DashboardKey | null>(null);

    const query = useQuery({
        queryKey: ['dashboard', range],
        queryFn: () => api<Dashboard>('/dashboard', { query: range }),
        placeholderData: keepPreviousData,
    });
    const d = query.data;

    const cards: { key: DashboardKey; label: string; icon: typeof Wallet; accent: string }[] = [
        { key: 'ca', label: t('stats.ca'), icon: BarChart3, accent: 'text-brand bg-brand-soft' },
        { key: 'regle', label: t('stats.regle'), icon: CheckCircle2, accent: 'text-ok bg-ok-soft' },
        { key: 'impaye', label: t('stats.impaye'), icon: AlertTriangle, accent: 'text-bad bg-bad-soft' },
        { key: 'caisse', label: t('stats.caisse'), icon: Wallet, accent: 'text-warn bg-warn-soft' },
    ];

    const presets: Preset[] = ['tout', 'aujourdhui', 'ce_mois', 'cette_annee', 'custom'];
    const presetLabel = (p: Preset) => (p === 'custom' ? t('spa.ui.periode') : t(`spa.ui.${p}`));

    return (
        <div className="space-y-4">
            <PageHeader
                title={t('spa.ui.tableau_de_bord')}
                actions={
                    <Link to="/pos" className="btn btn-success">
                        <ShoppingCart />
                        {t('spa.ui.caisse')}
                    </Link>
                }
            />

            {/* Period: one row of choices, dates only when asked for. */}
            <div className="flex flex-wrap items-center gap-2">
                <div role="radiogroup" aria-label={t('spa.ui.periode')} className="flex flex-wrap gap-1 rounded-ctl bg-surface p-1 ring-1 ring-line">
                    {presets.map((p) => (
                        <button
                            key={p}
                            role="radio"
                            aria-checked={preset === p}
                            onClick={() => {
                                setPreset(p);
                                if (p !== 'custom') setRange(rangeFor(p));
                            }}
                            className={`min-h-9 rounded-md px-3 text-sm font-semibold transition-colors ${preset === p ? 'bg-navy text-white' : 'text-ink-2 hover:bg-surface-2 hover:text-ink'}`}
                        >
                            {presetLabel(p)}
                        </button>
                    ))}
                </div>
                {preset === 'custom' && (
                    <div className="flex flex-wrap items-center gap-2 text-sm">
                        <label className="flex items-center gap-2">{t('spa.ui.du')}<input type="date" className="field min-h-9 w-auto" value={range.du} onChange={(e) => setRange((r) => ({ ...r, du: e.target.value }))} /></label>
                        <label className="flex items-center gap-2">{t('spa.ui.au')}<input type="date" className="field min-h-9 w-auto" value={range.au} onChange={(e) => setRange((r) => ({ ...r, au: e.target.value }))} /></label>
                    </div>
                )}
                {query.isFetching && <span className="spinner size-4 text-brand" />}
            </div>

            {/* The four figures; each opens the rows that add up to it. */}
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                {cards.map(({ key, label, icon: Icon, accent }) => (
                    <button key={key} onClick={() => setDetail(key)} className="panel group p-4 text-start transition-shadow hover:shadow-lift focus-visible:outline-3 focus-visible:outline-brand/35">
                        <div className="flex items-center justify-between">
                            <span className="text-sm font-semibold text-ink-2">{label}</span>
                            <span className={`grid size-9 place-items-center rounded-ctl ${accent}`}><Icon className="size-5" /></span>
                        </div>
                        <p className="mt-2 text-[1.7rem] leading-tight font-extrabold tracking-tight">
                            {d ? <Money value={d.stats[key]} /> : <span className="skeleton inline-block h-8 w-40 rounded" />}
                        </p>
                        <p className="mt-1 text-xs font-semibold text-brand opacity-0 transition-opacity group-hover:opacity-100">{t('stats.voir_details')} →</p>
                    </button>
                ))}
            </div>

            <div className="grid grid-cols-1 gap-3 xl:grid-cols-3">
                <div className="xl:col-span-2">
                    <ChartCard
                        title={t('chart.ca_mensuel')}
                        legend={<Legend items={[{ color: SERIES[0], label: t('chart.facture') }, { color: SERIES[1], label: t('chart.encaisse') }]} />}
                        chart={d ? (
                            <LineChart
                                labels={d.mensuel.labels}
                                series={[
                                    { label: t('chart.facture'), color: SERIES[0], values: d.mensuel.facture },
                                    { label: t('chart.encaisse'), color: SERIES[1], values: d.mensuel.encaisse },
                                ]}
                                format={money}
                            />
                        ) : <div className="skeleton h-60 rounded" />}
                        table={
                            <table className="w-full text-sm">
                                <thead><tr className="text-ink-2"><th className="py-1 text-start">{t('spa.ui.periode')}</th><th className="py-1 text-end">{t('chart.facture')}</th><th className="py-1 text-end">{t('chart.encaisse')}</th></tr></thead>
                                <tbody>{d?.mensuel.labels.map((l, i) => (
                                    <tr key={l} className="border-t border-line"><td className="num py-1.5">{l}</td><td className="py-1.5 text-end"><Money value={d.mensuel.facture[i]} /></td><td className="py-1.5 text-end"><Money value={d.mensuel.encaisse[i]} /></td></tr>
                                ))}</tbody>
                            </table>
                        }
                    />
                </div>

                <div className="grid gap-3">
                    <ChartCard
                        title={t('chart.statuts')}
                        chart={<ShareBar parts={(d?.statuts ?? []).map((s) => ({ key: s.statut, label: t(`statut.${s.statut}`), value: s.count, color: statutColor[s.statut] }))} empty={<p className="text-sm text-ink-3">{t('stats.aucune_ligne')}</p>} />}
                        table={<ul className="text-sm">{d?.statuts.map((s) => <li key={s.statut} className="flex justify-between border-b border-line py-1.5"><StatutBadge statut={s.statut} /><b className="num">{s.count}</b></li>)}</ul>}
                    />
                    <ChartCard
                        title={t('chart.top_clients')}
                        chart={<BarList items={(d?.top_clients ?? []).map((c) => ({ key: c.id, label: c.nom, value: c.total }))} format={money} empty={<p className="text-sm text-ink-3">{t('stats.aucune_ligne')}</p>} />}
                        table={<ul className="text-sm">{d?.top_clients.map((c) => <li key={c.id} className="flex justify-between gap-3 border-b border-line py-1.5"><span className="truncate">{c.nom}</span><Money value={c.total} className="font-bold" /></li>)}</ul>}
                    />
                </div>
            </div>

            <DetailDialog detailKey={detail} range={range} onClose={() => setDetail(null)} label={cards.find((c) => c.key === detail)?.label ?? ''} />
        </div>
    );
}

function DetailDialog({ detailKey, range, onClose, label }: { detailKey: DashboardKey | null; range: { du: string; au: string }; onClose: () => void; label: string }) {
    const t = useT();
    const query = useQuery({
        queryKey: ['dashboard', 'detail', detailKey, range],
        queryFn: () => api<DashboardDetail>(`/dashboard/${detailKey}`, { query: range }),
        enabled: detailKey !== null,
    });

    type Row = Record<string, string | number | null>;
    const invoiceCols: Column<Row>[] = [
        { key: 'numero', header: t('invoice.numero'), cell: (r) => <b>{r.numero}</b> },
        { key: 'date', header: t('invoice.date_facture'), cell: (r) => <DateText value={r.date as string} /> },
        { key: 'client', header: t('client.label'), cell: (r) => r.client ?? t('vente.client_passage') },
        { key: 'statut', header: t('invoice.statut'), cell: (r) => <StatutBadge statut={r.statut as InvoiceStatut} /> },
        { key: 'ttc', header: t('pdf.total_ttc'), align: 'end', cell: (r) => <Money value={r.total_ttc as number} /> },
        { key: 'reste', header: t('pdf.reste'), align: 'end', cell: (r) => <Money value={r.reste as number} className="font-bold" /> },
    ];
    const cols: Record<DashboardKey, Column<Row>[]> = {
        ca: invoiceCols,
        impaye: invoiceCols,
        regle: [
            { key: 'date', header: t('payment.date_paiement'), cell: (r) => <DateText value={r.date as string} /> },
            { key: 'numero', header: t('invoice.numero'), cell: (r) => r.numero ?? '—' },
            { key: 'client', header: t('client.label'), cell: (r) => r.client ?? t('vente.client_passage') },
            { key: 'mode', header: t('payment.mode'), cell: (r) => <ModeBadge mode={r.mode as PaymentMode} /> },
            { key: 'montant', header: t('payment.montant'), align: 'end', cell: (r) => <Money value={r.montant as number} className="font-bold" /> },
        ],
        caisse: [
            { key: 'at', header: t('caisse.heure'), cell: (r) => <DateText value={r.occurred_at as string} /> },
            { key: 'type', header: t('caisse.type'), cell: (r) => t(`caisse.${r.type}`) },
            { key: 'motif', header: t('caisse.motif'), cell: (r) => r.motif },
            { key: 'montant', header: t('caisse.montant'), align: 'end', cell: (r) => <Money value={(r.type === 'sortie' ? -1 : 1) * (r.montant as number)} className="font-bold" /> },
            { key: 'apres', header: t('caisse.solde_apres'), align: 'end', cell: (r) => <Money value={r.solde_apres as number} /> },
        ],
    };

    return (
        <Dialog open={detailKey !== null} onClose={onClose} title={label} size="lg">
            {detailKey && (
                <div className="-mx-5 -my-4">
                    <div className="flex items-center justify-between border-b border-line bg-surface-2 px-5 py-3 text-sm">
                        <span className="text-ink-2"><b className="num text-ink">{query.data?.count ?? '…'}</b> {t('stats.lignes')}</span>
                        <span className="text-ink-2">{t('stats.total')}: <Money value={query.data?.total} className="text-base font-extrabold text-ink" /></span>
                    </div>
                    <DataTable columns={cols[detailKey]} rows={query.data?.rows} rowKey={(r) => String(r.id)} loading={query.isPending} />
                    {query.data && query.data.count > query.data.rows.length && <p className="px-5 py-2 text-xs text-ink-3">{t('stats.tronque', { affichees: query.data.rows.length, total: query.data.count })}</p>}
                </div>
            )}
        </Dialog>
    );
}
