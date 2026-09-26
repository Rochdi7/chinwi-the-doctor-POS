import { useEffect, useRef, useState } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { Plus, Printer, Pencil, ArrowLeft, Banknote, CreditCard, Trash2, ShoppingCart, Download } from 'lucide-react';
import { useSession, useT } from '@/auth/session';
import { api, ApiError, errorMessage } from '@/lib/api';
import { useListParams } from '@/lib/useListParams';
import { useDebounced } from '@/lib/useDebounced';
import { useClientOptions, useList } from '@/lib/queries';
import { formatQty, parseAmount } from '@/lib/format';
import { Dialog } from '@/components/ui/Dialog';
import { toast } from '@/components/ui/toast';
import { DataTable, Pagination, type Column } from '@/components/ui/table';
import { DateRange, DateText, DeleteButton, FilterSelect, ModeBadge, Money, PageHeader, SearchBox, Section, StatutBadge, Toolbar } from '@/components/ui/misc';
import { SelectField, TextArea, TextField, fieldErrors } from '@/components/ui/form';
import type { Article, InvoiceDetail, InvoiceLine, InvoiceRow, InvoiceStatut, PaymentMode } from '@/types/api';

const defaults = { q: '', statut: '', client_id: '', du: '', au: '', sort: 'date_facture', dir: 'desc' };

export default function VentesPage() {
    const t = useT();
    const navigate = useNavigate();
    const [params, set] = useListParams(defaults);
    const [search, setSearch] = useState(params.q);
    const q = useDebounced(search, 300);
    useEffect(() => { if (q !== params.q) set({ q }); }, [q]); // eslint-disable-line react-hooks/exhaustive-deps

    const list = useList<InvoiceRow>('/ventes', params);
    const clients = useClientOptions();
    const [paying, setPaying] = useState<InvoiceRow | null>(null);

    const columns: Column<InvoiceRow>[] = [
        { key: 'numero', header: t('invoice.numero'), sort: 'numero', cell: (i) => <b className="num">{i.numero}</b> },
        { key: 'date', header: t('invoice.date_facture'), sort: 'date_facture', cell: (i) => <DateText value={i.date_facture} /> },
        { key: 'client', header: t('invoice.client'), cell: (i) => i.client ?? <span className="text-ink-3">{t('vente.client_passage')}</span> },
        { key: 'total', header: t('invoice.total_ttc'), sort: 'total_ttc', align: 'end', cell: (i) => <Money value={i.total_ttc} className="font-bold" /> },
        { key: 'paye', header: t('invoice.montant_paye'), align: 'end', hideBelow: 'md', cell: (i) => <Money value={i.montant_paye} /> },
        { key: 'statut', header: t('invoice.statut'), cell: (i) => <StatutBadge statut={i.statut} /> },
        {
            key: 'actions', header: '', align: 'end',
            cell: (i) => (
                <div className="flex justify-end gap-1" onClick={(e) => e.stopPropagation()}>
                    {i.reste > 0 && <button className="btn btn-secondary min-h-9 border-warn/40 px-2.5 text-sm text-warn-ink" onClick={() => setPaying(i)}><Banknote />{t('invoice.encaisser')}</button>}
                    {/* The thermal receipt of the sale, saved as a file (the server answers with Content-Disposition: attachment). */}
                    <a className="btn btn-secondary min-h-9 px-2.5 text-sm" href={i.recu_url} download title={t('receipt.telecharger')}><Download />{t('receipt.telecharger')}</a>
                    <a className="btn btn-ghost min-h-9 px-2.5" href={i.pdf_url} target="_blank" rel="noopener" title={t('invoice.print')}><Printer /></a>
                </div>
            ),
        },
    ];

    return (
        <div>
            <PageHeader
                title={t('invoice.plural')}
                actions={
                    <>
                        <Link to="/pos" className="btn btn-success"><ShoppingCart />{t('pos.label')}</Link>
                        <Link to="/ventes/nouvelle" className="btn btn-primary"><Plus />{t('spa.ui.nouvelle_vente')}</Link>
                    </>
                }
            />
            <section className="panel overflow-hidden">
                <Toolbar>
                    <SearchBox value={search} onChange={setSearch} />
                    <FilterSelect label={t('invoice.statut')} value={params.statut} onChange={(v) => set({ statut: v })}
                        options={[{ value: '', label: `${t('invoice.statut')}: ${t('spa.ui.tous')}` }, ...(['validee', 'partielle', 'payee'] as InvoiceStatut[]).map((s) => ({ value: s, label: t(`statut.${s}`) }))]} />
                    <FilterSelect label={t('invoice.client')} value={params.client_id} onChange={(v) => set({ client_id: v })}
                        options={[{ value: '', label: `${t('invoice.client')}: ${t('spa.ui.tous')}` }, ...(clients.data ?? []).map((c) => ({ value: String(c.id), label: c.raison_sociale }))]} />
                    <DateRange du={params.du} au={params.au} onChange={set} />
                </Toolbar>
                <DataTable
                    columns={columns} rows={list.data?.data} rowKey={(i) => i.id} loading={list.isPending} refreshing={list.isFetching && list.isPlaceholderData}
                    onRowClick={(i) => navigate(`/ventes/${i.id}`)} sort={params.sort} dir={params.dir as 'asc' | 'desc'} onSort={(sort, dir) => set({ sort, dir })}
                />
                <Pagination page={list.data} onPage={(n) => set({ page: String(n) })} />
            </section>
            {paying && <EncaisserDialog invoice={paying} onClose={() => setPaying(null)} />}
        </div>
    );
}

/** "Encaisser": one payment, at most what is still owed (checked by Laravel too). */
function EncaisserDialog({ invoice, onClose }: { invoice: Pick<InvoiceRow, 'id' | 'numero' | 'reste'>; onClose: () => void }) {
    const t = useT();
    const { devise } = useSession();
    const queryClient = useQueryClient();
    const [montant, setMontant] = useState(String(invoice.reste));
    const [mode, setMode] = useState<PaymentMode>('especes');
    const [error, setError] = useState<string | null>(null);
    const [busy, setBusy] = useState(false);
    // Two quick Enters run before the re-render that disables the button.
    const sending = useRef(false);

    const submit = async () => {
        if (sending.current) return;
        const value = parseAmount(montant);
        if (value === null) return setError(t('spa.erreur.validation'));
        sending.current = true;
        setBusy(true);
        setError(null);
        try {
            const r = await api<{ payment: { pdf_url: string } }>(`/ventes/${invoice.id}/encaisser`, { method: 'POST', body: { montant: value, mode } });
            toast.success(t('vente.paye'));
            window.open(r.payment.pdf_url, '_blank', 'noopener');
            for (const k of ['/ventes', '/reglements', '/clients', 'dashboard']) void queryClient.invalidateQueries({ queryKey: [k] });
            onClose();
        } catch (e) {
            setError(e instanceof ApiError && e.kind === 'validation' ? e.firstError() : errorMessage(e, t));
        } finally {
            sending.current = false;
            setBusy(false);
        }
    };

    return (
        <Dialog
            open onClose={onClose} size="sm" title={`${t('invoice.encaisser')} — ${invoice.numero}`} description={<>{t('invoice.reste')} : <Money value={invoice.reste} /></>}
            footer={<><button className="btn btn-secondary" onClick={onClose}>{t('spa.ui.annuler')}</button><button className="btn btn-success" disabled={busy} onClick={submit}>{busy && <span className="spinner size-4" />}{t('invoice.encaisser')}</button></>}
        >
            <form className="space-y-4" onSubmit={(e) => { e.preventDefault(); void submit(); }}>
                <TextField label={t('payment.montant')} value={montant} onChange={setMontant} error={error} inputMode="decimal" suffix={devise} data-autofocus />
                <div role="radiogroup" aria-label={t('payment.mode')} className="grid grid-cols-2 gap-2">
                    {(['especes', 'tpe'] as PaymentMode[]).map((m) => (
                        <button key={m} type="button" role="radio" aria-checked={mode === m} onClick={() => setMode(m)}
                            className={`btn ${mode === m ? 'btn-secondary border-brand bg-brand-soft text-brand ring-1 ring-brand' : 'btn-secondary'}`}>
                            {m === 'especes' ? <Banknote /> : <CreditCard />}{t(`mode.${m}`)}
                        </button>
                    ))}
                </div>
            </form>
        </Dialog>
    );
}

/** One sale: lines, totals, payments, Encaisser, print, edit, delete. */
export function VenteDetailPage() {
    const t = useT();
    const { id } = useParams();
    const navigate = useNavigate();
    const queryClient = useQueryClient();
    const [paying, setPaying] = useState(false);
    const query = useQuery({ queryKey: ['/ventes', id], queryFn: () => api<InvoiceDetail>(`/ventes/${id}`) });
    const v = query.data;

    if (!v) return <div className="grid h-60 place-items-center"><span className="spinner size-6 text-brand" /></div>;

    const refresh = () => { for (const k of ['/ventes', '/reglements', '/clients', 'dashboard']) void queryClient.invalidateQueries({ queryKey: [k] }); };

    return (
        <div className="space-y-4">
            <Link to="/ventes" className="btn btn-ghost -ms-2 min-h-9 px-2 text-sm"><ArrowLeft className="rtl:-scale-x-100" />{t('invoice.plural')}</Link>
            <PageHeader
                title={<span className="flex flex-wrap items-center gap-3">{t('invoice.label')} <span className="num">{v.numero}</span> <StatutBadge statut={v.statut} /></span>}
                subtitle={<><DateText value={v.date_facture} /> · {v.client ?? t('vente.client_passage')}{v.user ? ` · ${v.user}` : ''}</>}
                actions={
                    <>
                        {v.reste > 0 && <button className="btn btn-success" onClick={() => setPaying(true)}><Banknote />{t('invoice.encaisser')}</button>}
                        <a className="btn btn-primary" href={v.recu_url} download><Download />{t('receipt.telecharger')}</a>
                        <a className="btn btn-secondary" href={v.pdf_url} target="_blank" rel="noopener"><Printer />{t('invoice.print')}</a>
                        <Link to={`/ventes/${v.id}/modifier`} className="btn btn-secondary"><Pencil />{t('spa.ui.modifier')}</Link>
                        <DeleteButton label={v.numero} onConfirm={async () => {
                            try { await api(`/ventes/${v.id}`, { method: 'DELETE' }); } catch (e) { toast.error(errorMessage(e, t)); throw e; }
                            toast.success(t('spa.ui.supprime'));
                            refresh();
                            navigate('/ventes');
                        }} />
                    </>
                }
            />

            <div className="grid gap-3 lg:grid-cols-3">
                <Section title={t('invoice.items')} className="overflow-hidden lg:col-span-2">
                    <DataTable
                        columns={[
                            { key: 'd', header: t('item.designation'), cell: (l) => <b>{l.designation}</b> },
                            { key: 'q', header: t('item.quantite'), align: 'end', cell: (l) => <span className="num">{formatQty(l.quantite)}</span> },
                            { key: 'p', header: t('item.prix_unitaire'), align: 'end', cell: (l) => <Money value={l.prix_unitaire} /> },
                            { key: 'r', header: t('item.remise'), align: 'end', hideBelow: 'md', cell: (l) => (l.remise > 0 ? <Money value={l.remise} className="text-bad" /> : '—') },
                            { key: 'v', header: t('item.tva'), align: 'end', hideBelow: 'md', cell: (l) => <span className="num">{l.tva}%</span> },
                            { key: 't', header: t('item.total_ttc'), align: 'end', cell: (l) => <Money value={l.total_ttc} className="font-bold" /> },
                        ]}
                        rows={v.items}
                        rowKey={(l) => l.id ?? l.designation}
                    />
                    {v.note && <p className="border-t border-line px-4 py-3 text-sm text-ink-2">{v.note}</p>}
                </Section>

                <Section title={t('pdf.grand_total')}>
                    <dl className="space-y-2 p-4 text-sm">
                        <div className="flex justify-between"><dt className="text-ink-2">{t('invoice.total_ht')}</dt><dd><Money value={v.total_ht} /></dd></div>
                        <div className="flex justify-between"><dt className="text-ink-2">{t('invoice.total_tva')}</dt><dd><Money value={v.total_tva} /></dd></div>
                        <div className="flex items-center justify-between rounded-ctl bg-navy px-3 py-2.5 text-white"><dt className="font-bold">{t('invoice.total_ttc')}</dt><dd><Money value={v.total_ttc} className="text-xl font-extrabold" /></dd></div>
                        <div className="flex justify-between pt-1"><dt className="text-ink-2">{t('invoice.montant_paye')}</dt><dd><Money value={v.montant_paye} className="font-semibold text-ok" /></dd></div>
                        <div className="flex justify-between"><dt className="font-bold">{t('invoice.reste')}</dt><dd><Money value={v.reste} className={`font-extrabold ${v.reste > 0 ? 'text-bad' : ''}`} /></dd></div>
                    </dl>
                </Section>
            </div>

            <Section title={t('payment.plural')} className="overflow-hidden">
                <DataTable
                    columns={[
                        { key: 'd', header: t('payment.date_paiement'), cell: (p) => <DateText value={p.date} /> },
                        { key: 'm', header: t('payment.mode'), cell: (p) => <ModeBadge mode={p.mode} /> },
                        { key: 'r', header: t('payment.reference'), hideBelow: 'sm', cell: (p) => (p.reference ? <bdi dir="ltr">{p.reference}</bdi> : '—') },
                        { key: 'x', header: t('payment.montant'), align: 'end', cell: (p) => <Money value={p.montant} className="font-bold" /> },
                        {
                            key: 'a', header: '', align: 'end', cell: (p) => (
                                <div className="flex justify-end gap-1">
                                    <a className="btn btn-ghost min-h-9 px-2.5" href={p.pdf_url} target="_blank" rel="noopener" title={t('receipt.print')}><Printer /></a>
                                    <DeleteButton compact onConfirm={async () => {
                                        try { await api(`/reglements/${p.id}`, { method: 'DELETE' }); } catch (e) { toast.error(errorMessage(e, t)); throw e; }
                                        toast.success(t('spa.ui.supprime'));
                                        refresh();
                                    }} />
                                </div>
                            ),
                        },
                    ]}
                    rows={v.payments}
                    rowKey={(p) => p.id}
                />
            </Section>

            {paying && <EncaisserDialog invoice={v} onClose={() => setPaying(false)} />}
        </div>
    );
}

/** GET /ventes/{id} when editing, /ventes/nouveau when creating. */
type VenteSource = InvoiceDetail | { numero: string; date_facture: string };

type Line = { key: string; id?: number; article_id: number | null; designation: string; quantite: string; prix_unitaire: string; remise: string; tva: string };

let lineSeq = 0;
const blank = (): Line => ({ key: `n${++lineSeq}`, article_id: null, designation: '', quantite: '1', prix_unitaire: '0', remise: '0', tva: '0' });

/**
 * Search box with its results beneath, the same panel as the Combobox:
 * arrow keys, Enter, Escape, closes on a click elsewhere.
 */
function ArticlePicker({ value, onChange, results, onPick }: { value: string; onChange: (v: string) => void; results: Article[] | undefined; onPick: (a: Article) => void }) {
    const t = useT();
    const box = useRef<HTMLDivElement>(null);
    const [active, setActive] = useState(0);
    const [closed, setClosed] = useState(false);
    const open = !closed && results !== undefined;

    useEffect(() => { setActive(0); setClosed(false); }, [results]);
    useEffect(() => {
        if (!open) return;
        const onDown = (e: MouseEvent) => { if (!box.current?.contains(e.target as Node)) setClosed(true); };
        document.addEventListener('mousedown', onDown);
        return () => document.removeEventListener('mousedown', onDown);
    }, [open]);

    const pick = (a: Article) => { onPick(a); setClosed(true); };

    return (
        <div ref={box} className="relative">
            <input
                className="field"
                value={value}
                onChange={(e) => onChange(e.target.value)}
                onFocus={() => setClosed(false)}
                onKeyDown={(e) => {
                    if (!open || !results) return;
                    if (e.key === 'ArrowDown') { e.preventDefault(); setActive((a) => Math.min(results.length - 1, a + 1)); }
                    else if (e.key === 'ArrowUp') { e.preventDefault(); setActive((a) => Math.max(0, a - 1)); }
                    else if (e.key === 'Enter') { e.preventDefault(); const a = results[active]; if (a) pick(a); }
                    else if (e.key === 'Escape') { e.preventDefault(); setClosed(true); }
                }}
                placeholder={t('pos.recherche')}
                aria-label={t('invoice.ajouter_article')}
                aria-expanded={open}
                autoComplete="off"
                spellCheck={false}
            />
            {open && results && (
                <ul role="listbox" className="absolute inset-x-0 top-full z-20 mt-1 max-h-80 overflow-y-auto rounded-card border border-line bg-surface py-1 shadow-lift">
                    {results.length === 0 && <li className="px-3 py-3 text-sm text-ink-3">{t('invoice.aucun_article_trouve')}</li>}
                    {results.map((a, i) => (
                        <li
                            key={a.id}
                            role="option"
                            aria-selected={i === active}
                            onMouseEnter={() => setActive(i)}
                            onClick={() => pick(a)}
                            className={`flex min-h-11 cursor-pointer items-center justify-between gap-3 px-3 text-[0.95rem] ${i === active ? 'bg-brand-soft text-brand' : ''}`}
                        >
                            <span className="min-w-0 truncate"><b>{a.designation}</b> <span className="num text-xs text-ink-3">{a.reference}</span></span>
                            <Money value={a.prix_vente} />
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

/**
 * The back-office sale form (was the Filament "Ventes" form): lines with a
 * free price and discount, optional client, optional payment on creation.
 * Stock, totals and status are computed by Laravel on save.
 */
export function VenteFormPage() {
    const t = useT();
    const { devise } = useSession();
    const { id } = useParams();
    const editing = id !== undefined;
    const navigate = useNavigate();
    const queryClient = useQueryClient();
    const clients = useClientOptions();

    const [header, setHeader] = useState<{ numero: string; date_facture: string; client_id: string; note: string } | null>(null);
    const [lines, setLines] = useState<Line[]>([]);
    const [pay, setPay] = useState({ encaisser: true, mode: 'especes' as PaymentMode, montant_recu: '' });
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [busy, setBusy] = useState(false);
    const [picker, setPicker] = useState('');
    const pickerQ = useDebounced(picker, 250);

    const init = useQuery<VenteSource>({
        queryKey: ['vente-form', id ?? 'new'],
        queryFn: () => api<VenteSource>(editing ? `/ventes/${id}` : '/ventes/nouveau'),
        gcTime: 0,
    });

    useEffect(() => {
        const d = init.data;
        if (!d || header) return;
        if ('items' in d) {
            setHeader({ numero: d.numero, date_facture: d.date_facture ?? '', client_id: d.client_id ? String(d.client_id) : '', note: d.note ?? '' });
            setLines(d.items.map((l: InvoiceLine) => ({ key: `e${l.id}`, id: l.id, article_id: l.article_id, designation: l.designation, quantite: String(l.quantite), prix_unitaire: String(l.prix_unitaire), remise: String(l.remise), tva: String(l.tva) })));
        } else {
            setHeader({ numero: d.numero, date_facture: d.date_facture, client_id: '', note: '' });
            setLines([]);
        }
    }, [init.data]); // eslint-disable-line react-hooks/exhaustive-deps

    const found = useQuery({
        queryKey: ['/pos/articles', pickerQ],
        queryFn: () => api<{ data: Article[] }>('/pos/articles', { query: { recherche: pickerQ } }).then((r) => r.data),
        enabled: pickerQ.trim().length > 0,
    });

    const addArticle = (a: Article) => {
        setLines((ls) => [...ls, { ...blank(), article_id: a.id, designation: a.designation, prix_unitaire: String(a.prix_vente), tva: String(a.tva) }]);
        setPicker('');
    };
    const patch = (key: string, p: Partial<Line>) => setLines((ls) => ls.map((l) => (l.key === key ? { ...l, ...p } : l)));
    const num = (s: string) => Number(s.replace(',', '.')) || 0;

    const submit = async () => {
        if (!header) return;
        setBusy(true);
        setErrors({});
        const body = {
            ...header,
            client_id: header.client_id ? Number(header.client_id) : null,
            note: header.note || null,
            items: lines.map((l) => ({ id: l.id, article_id: l.article_id, designation: l.designation, quantite: num(l.quantite), prix_unitaire: num(l.prix_unitaire), remise: num(l.remise), tva: num(l.tva) })),
            ...(editing ? {} : { encaisser: pay.encaisser, mode: pay.mode, montant_recu: pay.montant_recu === '' ? null : parseAmount(pay.montant_recu) }),
        };
        try {
            const r = await api<{ id?: number; invoice?: { id: number }; payment?: { pdf_url: string } | null }>(editing ? `/ventes/${id}` : '/ventes', { method: editing ? 'PUT' : 'POST', body });
            toast.success(t('spa.ui.enregistre'));
            if (r.payment) window.open(r.payment.pdf_url, '_blank', 'noopener');
            for (const k of ['/ventes', '/reglements', '/clients', '/articles', 'dashboard']) void queryClient.invalidateQueries({ queryKey: [k] });
            navigate(`/ventes/${editing ? id : r.invoice?.id}`);
        } catch (e) {
            if (e instanceof ApiError && e.kind === 'validation') { setErrors(fieldErrors(e.errors)); toast.error(t('spa.erreur.validation')); }
            else toast.error(errorMessage(e, t));
        } finally {
            setBusy(false);
        }
    };

    if (!header) return <div className="grid h-60 place-items-center"><span className="spinner size-6 text-brand" /></div>;

    return (
        <div className="space-y-4">
            <Link to={editing ? `/ventes/${id}` : '/ventes'} className="btn btn-ghost -ms-2 min-h-9 px-2 text-sm"><ArrowLeft className="rtl:-scale-x-100" />{t('spa.ui.retour')}</Link>
            <PageHeader title={editing ? `${t('spa.ui.modifier')} — ${header.numero}` : t('spa.ui.nouvelle_vente')} />

            <Section>
                <div className="grid gap-4 p-4 sm:grid-cols-3">
                    <SelectField label={t('invoice.client')} value={header.client_id} onChange={(v) => setHeader({ ...header, client_id: v })} error={errors.client_id}
                        placeholder={t('vente.client_passage')} options={(clients.data ?? []).map((c) => ({ value: String(c.id), label: c.raison_sociale }))} />
                    <TextField label={t('invoice.date_facture')} type="date" dir="ltr" value={header.date_facture} onChange={(v) => setHeader({ ...header, date_facture: v })} error={errors.date_facture} />
                    <TextField label={t('invoice.numero')} value={header.numero} onChange={(v) => setHeader({ ...header, numero: v })} error={errors.numero} dir="ltr" />
                </div>
            </Section>

            <Section title={t('spa.ui.lignes_vente')} className="overflow-visible">
                <div className="space-y-3 p-4">
                    <ArticlePicker value={picker} onChange={setPicker} results={pickerQ ? found.data : undefined} onPick={addArticle} />

                    {lines.length > 0 && (
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[42rem] text-sm">
                                <thead>
                                    <tr className="text-xs font-bold text-ink-2 uppercase rtl:normal-case">
                                        <th className="px-1 py-2 text-start">{t('item.designation')}</th>
                                        <th className="w-24 px-1 py-2 text-end">{t('item.quantite')}</th>
                                        <th className="w-28 px-1 py-2 text-end">{t('item.prix_unitaire')}</th>
                                        <th className="w-24 px-1 py-2 text-end">{t('item.remise')}</th>
                                        <th className="w-20 px-1 py-2 text-end">{t('item.tva')}</th>
                                        <th className="w-10" />
                                    </tr>
                                </thead>
                                <tbody>
                                    {lines.map((l, i) => (
                                        <tr key={l.key} className="border-t border-line align-top">
                                            <td className="px-1 py-1.5"><input className={`field min-h-10 ${errors[`items.${i}.designation`] ? 'border-bad' : ''}`} value={l.designation} onChange={(e) => patch(l.key, { designation: e.target.value })} aria-label={t('item.designation')} /></td>
                                            {(['quantite', 'prix_unitaire', 'remise', 'tva'] as const).map((f) => (
                                                <td key={f} className="px-1 py-1.5">
                                                    <input className={`field min-h-10 text-end tabular-nums ${errors[`items.${i}.${f}`] ? 'border-bad' : ''}`} inputMode="decimal" value={l[f]} onChange={(e) => patch(l.key, { [f]: e.target.value })} aria-label={t(`item.${f}`)} title={errors[`items.${i}.${f}`]} />
                                                </td>
                                            ))}
                                            <td className="px-1 py-1.5"><button type="button" className="btn btn-danger-ghost min-h-10 px-2" onClick={() => setLines((ls) => ls.filter((x) => x.key !== l.key))} aria-label={t('pos.retirer')}><Trash2 /></button></td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                    {Object.entries(errors).filter(([k]) => k.startsWith('items')).slice(0, 3).map(([k, m]) => <p key={k} className="text-sm font-medium text-bad">{m}</p>)}
                    <button type="button" className="btn btn-ghost min-h-9 text-sm" onClick={() => setLines((ls) => [...ls, blank()])}><Plus />{t('spa.ui.ligne_libre')}</button>
                </div>
            </Section>

            {!editing && (
                <Section title={t('vente.section')}>
                    <div className="grid gap-4 p-4 sm:grid-cols-3">
                        <SelectField label={t('vente.encaisse_maintenant')} value={pay.encaisser ? '1' : '0'} onChange={(v) => setPay({ ...pay, encaisser: v === '1' })} options={[{ value: '1', label: t('spa.ui.oui') }, { value: '0', label: t('spa.ui.non') }]} hint={t('vente.rien_paye')} />
                        {pay.encaisser && <SelectField label={t('payment.mode')} value={pay.mode} onChange={(v) => setPay({ ...pay, mode: v as PaymentMode })} options={[{ value: 'especes', label: t('mode.especes') }, { value: 'tpe', label: t('mode.tpe') }]} />}
                        {pay.encaisser && <TextField label={t('vente.montant_recu')} value={pay.montant_recu} onChange={(v) => setPay({ ...pay, montant_recu: v })} inputMode="decimal" suffix={devise} hint={t('pos.montant_recu_aide')} />}
                    </div>
                </Section>
            )}

            <Section><div className="p-4"><TextArea label={t('invoice.note')} value={header.note} onChange={(v) => setHeader({ ...header, note: v })} /></div></Section>

            <div className="sticky bottom-0 -mx-3 flex justify-end gap-2 border-t border-line bg-canvas/95 px-3 py-3 backdrop-blur sm:-mx-5 sm:px-5">
                <Link to={editing ? `/ventes/${id}` : '/ventes'} className="btn btn-secondary">{t('spa.ui.annuler')}</Link>
                <button className="btn btn-primary min-w-40" disabled={busy} onClick={submit}>{busy && <span className="spinner size-4" />}{t('spa.ui.enregistrer')}</button>
            </div>
        </div>
    );
}
