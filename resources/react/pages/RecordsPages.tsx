import { useEffect, useState } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { useNavigate } from 'react-router-dom';
import { Printer, Pencil, Save, Eye } from 'lucide-react';
import { useT } from '@/auth/session';
import { api, ApiError, errorMessage } from '@/lib/api';
import { useListParams } from '@/lib/useListParams';
import { useDebounced } from '@/lib/useDebounced';
import { useList } from '@/lib/queries';
import { parseAmount } from '@/lib/format';
import { Dialog } from '@/components/ui/Dialog';
import { toast } from '@/components/ui/toast';
import { DataTable, Pagination, type Column } from '@/components/ui/table';
import { Badge, DateRange, DateText, DeleteButton, FilterSelect, ModeBadge, Money, PageHeader, SearchBox, Section, Toolbar } from '@/components/ui/misc';
import { SelectField, TextArea, TextField, fieldErrors } from '@/components/ui/form';
import type { CaisseRow, JournalRow, Paginated, PaymentMode, PaymentRow, Settings } from '@/types/api';

// ---- Règlements --------------------------------------------------------------

export function ReglementsPage() {
    const t = useT();
    const navigate = useNavigate();
    const queryClient = useQueryClient();
    const [params, set] = useListParams({ q: '', mode: '', du: '', au: '' });
    const [search, setSearch] = useState(params.q);
    const q = useDebounced(search, 300);
    useEffect(() => { if (q !== params.q) set({ q }); }, [q]); // eslint-disable-line react-hooks/exhaustive-deps
    const list = useList<PaymentRow>('/reglements', params);
    const [editing, setEditing] = useState<PaymentRow | null>(null);

    const columns: Column<PaymentRow>[] = [
        { key: 'd', header: t('payment.date_paiement'), cell: (p) => <DateText value={p.date_paiement} /> },
        { key: 'c', header: t('payment.client'), cell: (p) => p.client ?? <span className="text-ink-3">{t('vente.client_passage')}</span> },
        { key: 'n', header: t('payment.invoice'), cell: (p) => <span className="num">{p.numero ?? '—'}</span> },
        { key: 'm', header: t('payment.mode'), cell: (p) => <ModeBadge mode={p.mode} /> },
        { key: 'x', header: t('payment.montant'), align: 'end', cell: (p) => <Money value={p.montant} className="font-bold text-ok" /> },
        {
            key: 'a', header: '', align: 'end', cell: (p) => (
                <div className="flex justify-end gap-1" onClick={(e) => e.stopPropagation()}>
                    <a className="btn btn-ghost min-h-9 px-2.5" href={p.pdf_url} target="_blank" rel="noopener" title={t('receipt.print')}><Printer /></a>
                    <button className="btn btn-ghost min-h-9 px-2.5" onClick={() => setEditing(p)} title={t('spa.ui.modifier')}><Pencil /></button>
                    <DeleteButton compact onConfirm={async () => {
                        try { await api(`/reglements/${p.id}`, { method: 'DELETE' }); } catch (e) { toast.error(errorMessage(e, t)); throw e; }
                        toast.success(t('spa.ui.supprime'));
                        for (const k of ['/reglements', '/ventes', '/clients', 'dashboard']) void queryClient.invalidateQueries({ queryKey: [k] });
                    }} />
                </div>
            ),
        },
    ];

    return (
        <div>
            <PageHeader title={t('payment.plural')} />
            <section className="panel overflow-hidden">
                <Toolbar>
                    <SearchBox value={search} onChange={setSearch} />
                    <FilterSelect label={t('payment.mode')} value={params.mode} onChange={(v) => set({ mode: v })} options={[{ value: '', label: `${t('payment.mode')}: ${t('spa.ui.tous')}` }, { value: 'especes', label: t('mode.especes') }, { value: 'tpe', label: t('mode.tpe') }]} />
                    <DateRange du={params.du} au={params.au} onChange={set} />
                </Toolbar>
                <DataTable columns={columns} rows={list.data?.data} rowKey={(p) => p.id} loading={list.isPending} refreshing={list.isFetching && list.isPlaceholderData}
                    onRowClick={(p) => (p.invoice_id ? navigate(`/ventes/${p.invoice_id}`) : setEditing(p))} />
                <Pagination page={list.data} onPage={(n) => set({ page: String(n) })} />
            </section>
            {editing && <PaymentForm payment={editing} onClose={() => setEditing(null)} />}
        </div>
    );
}

function PaymentForm({ payment, onClose }: { payment: PaymentRow; onClose: () => void }) {
    const t = useT();
    const queryClient = useQueryClient();
    const [form, setForm] = useState({ montant: String(payment.montant), date_paiement: payment.date_paiement ?? '', mode: payment.mode, reference: payment.reference ?? '' });
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [busy, setBusy] = useState(false);

    const submit = async () => {
        setBusy(true);
        setErrors({});
        try {
            await api(`/reglements/${payment.id}`, { method: 'PUT', body: { ...form, montant: parseAmount(form.montant), reference: form.reference || null, client_id: payment.client_id, invoice_id: payment.invoice_id } });
            toast.success(t('spa.ui.enregistre'));
            for (const k of ['/reglements', '/ventes', '/clients', 'dashboard']) void queryClient.invalidateQueries({ queryKey: [k] });
            onClose();
        } catch (e) {
            if (e instanceof ApiError && e.kind === 'validation') setErrors(fieldErrors(e.errors));
            else toast.error(errorMessage(e, t));
        } finally {
            setBusy(false);
        }
    };

    return (
        <Dialog open onClose={onClose} size="sm" title={`${t('payment.label')} #${payment.id}`} description={payment.numero ? `${t('payment.invoice')} ${payment.numero}` : undefined}
            footer={<><button className="btn btn-secondary" onClick={onClose}>{t('spa.ui.annuler')}</button><button className="btn btn-primary" disabled={busy} onClick={submit}>{t('spa.ui.enregistrer')}</button></>}>
            <form className="space-y-4" onSubmit={(e) => { e.preventDefault(); void submit(); }}>
                <TextField label={t('payment.montant')} value={form.montant} onChange={(v) => setForm({ ...form, montant: v })} error={errors.montant} inputMode="decimal" suffix="DH" data-autofocus />
                <TextField label={t('payment.date_paiement')} type="date" value={form.date_paiement} onChange={(v) => setForm({ ...form, date_paiement: v })} error={errors.date_paiement} />
                <SelectField label={t('payment.mode')} value={form.mode} onChange={(v) => setForm({ ...form, mode: v as PaymentMode })} options={[{ value: 'especes', label: t('mode.especes') }, { value: 'tpe', label: t('mode.tpe') }]} />
                <TextField label={t('payment.reference')} value={form.reference} onChange={(v) => setForm({ ...form, reference: v })} error={errors.reference} />
            </form>
        </Dialog>
    );
}

// ---- Caisse (read only) ---------------------------------------------------------

export function CaissePage() {
    const t = useT();
    const [params, set] = useListParams({ type: '', du: '', au: '' });
    const query = useQuery({
        queryKey: ['/caisse', params],
        queryFn: () => api<Paginated<CaisseRow> & { solde: number }>('/caisse', { query: params }),
        placeholderData: (prev) => prev,
    });

    const columns: Column<CaisseRow>[] = [
        { key: 'h', header: t('caisse.heure'), cell: (m) => <DateText value={m.occurred_at} /> },
        { key: 't', header: t('caisse.type'), cell: (m) => <Badge tone={m.type === 'entree' ? 'ok' : 'bad'}>{t(`caisse.${m.type}`)}</Badge> },
        { key: 'x', header: t('caisse.montant'), align: 'end', cell: (m) => <Money value={m.type === 'sortie' ? -m.montant : m.montant} className={`font-bold ${m.type === 'entree' ? 'text-ok' : 'text-bad'}`} /> },
        { key: 'm', header: t('caisse.motif'), cell: (m) => m.motif },
        { key: 'u', header: t('log.user'), hideBelow: 'md', cell: (m) => m.user ?? '—' },
        { key: 's', header: t('caisse.solde_apres'), align: 'end', cell: (m) => <Money value={m.solde_apres} /> },
    ];

    return (
        <div>
            <PageHeader title={t('caisse.mouvements')} actions={
                <div className="panel flex items-center gap-3 px-4 py-2">
                    <span className="text-sm font-semibold text-ink-2">{t('caisse.solde')}</span>
                    <Money value={query.data?.solde} className="text-xl font-extrabold" />
                </div>
            } />
            <section className="panel overflow-hidden">
                <Toolbar>
                    <FilterSelect label={t('caisse.type')} value={params.type} onChange={(v) => set({ type: v })} options={[{ value: '', label: `${t('caisse.type')}: ${t('spa.ui.tous')}` }, { value: 'entree', label: t('caisse.entree') }, { value: 'sortie', label: t('caisse.sortie') }]} />
                    <DateRange du={params.du} au={params.au} onChange={set} />
                </Toolbar>
                <DataTable columns={columns} rows={query.data?.data} rowKey={(m) => m.id} loading={query.isPending} refreshing={query.isFetching && query.isPlaceholderData} />
                <Pagination page={query.data} onPage={(n) => set({ page: String(n) })} />
            </section>
        </div>
    );
}

// ---- Journal (audit trail) ------------------------------------------------------

export function JournalPage() {
    const t = useT();
    const [params, set] = useListParams({ q: '', event: '', du: '', au: '' });
    const [search, setSearch] = useState(params.q);
    const q = useDebounced(search, 300);
    useEffect(() => { if (q !== params.q) set({ q }); }, [q]); // eslint-disable-line react-hooks/exhaustive-deps
    const list = useList<JournalRow>('/journal', params);
    const events = useQuery({ queryKey: ['/journal/evenements'], queryFn: () => api<{ data: string[] }>('/journal/evenements').then((r) => r.data) });
    const [open, setOpen] = useState<JournalRow | null>(null);

    const eventLabel = (e: string) => { const l = t(`event.${e}`); return l === `event.${e}` ? e : l; };
    const tone = (e: string) => (e.startsWith('caisse') ? 'warn' : e === 'deleted' ? 'bad' : e === 'created' ? 'ok' : 'brand') as 'warn' | 'bad' | 'ok' | 'brand';

    const columns: Column<JournalRow>[] = [
        { key: 'h', header: t('log.heure'), cell: (l) => <b><DateText value={l.occurred_at} /></b> },
        { key: 'u', header: t('log.user'), cell: (l) => l.user ?? '—' },
        { key: 'e', header: t('log.event'), cell: (l) => <Badge tone={tone(l.event)}>{eventLabel(l.event)}</Badge> },
        { key: 's', header: t('log.subject'), hideBelow: 'md', cell: (l) => (l.subject_type ? `${l.subject_type} #${l.subject_id}` : '—') },
        {
            key: 'c', header: t('log.changement'), cell: (l) => (
                <div className="max-w-md space-y-0.5 text-xs">
                    {l.changes.slice(0, 3).map((c, i) => <p key={i} className="truncate"><b>{c.champ}</b> : <span className="text-ink-3 line-through">{c.avant}</span> → <b>{c.apres}</b>{c.delta && <span className={c.delta.startsWith('+') ? ' text-ok' : ' text-bad'}> ({c.delta})</span>}</p>)}
                    {l.changes.length > 3 && <p className="text-ink-3">+{l.changes.length - 3}</p>}
                    {l.changes.length === 0 && <span className="text-ink-3">{l.description ?? '—'}</span>}
                </div>
            ),
        },
        { key: 'r', header: t('log.remise'), hideBelow: 'lg', cell: (l) => (l.remise ? <Badge tone="bad">{l.remise}</Badge> : '—') },
        { key: 'v', header: '', align: 'end', cell: () => <Eye className="size-4 text-ink-3" /> },
    ];

    return (
        <div>
            <PageHeader title={t('log.plural')} />
            <section className="panel overflow-hidden">
                <Toolbar>
                    <SearchBox value={search} onChange={setSearch} />
                    <FilterSelect label={t('log.event')} value={params.event} onChange={(v) => set({ event: v })} options={[{ value: '', label: `${t('log.event')}: ${t('spa.ui.tous')}` }, ...(events.data ?? []).map((e) => ({ value: e, label: eventLabel(e) }))]} />
                    <DateRange du={params.du} au={params.au} onChange={set} />
                </Toolbar>
                <DataTable columns={columns} rows={list.data?.data} rowKey={(l) => l.id} loading={list.isPending} refreshing={list.isFetching && list.isPlaceholderData} onRowClick={setOpen} />
                <Pagination page={list.data} onPage={(n) => set({ page: String(n) })} />
            </section>

            <Dialog open={open !== null} onClose={() => setOpen(null)} size="lg" title={t('log.comparaison')}>
                {open && (
                    <div className="space-y-4">
                        <dl className="grid grid-cols-2 gap-3 text-sm sm:grid-cols-3">
                            <div><dt className="text-ink-3">{t('log.heure')}</dt><dd className="font-semibold"><DateText value={open.occurred_at} /></dd></div>
                            <div><dt className="text-ink-3">{t('log.user')}</dt><dd className="font-semibold">{open.user ?? '—'}</dd></div>
                            <div><dt className="text-ink-3">{t('log.event')}</dt><dd><Badge tone={tone(open.event)}>{eventLabel(open.event)}</Badge></dd></div>
                            <div><dt className="text-ink-3">{t('log.subject')}</dt><dd className="font-semibold">{open.subject_type ? `${open.subject_type} #${open.subject_id}` : '—'}</dd></div>
                            <div><dt className="text-ink-3">{t('log.montant')}</dt><dd className="font-semibold"><Money value={open.montant} /></dd></div>
                            <div><dt className="text-ink-3">{t('log.ip')}</dt><dd className="num font-semibold">{open.ip ?? '—'}</dd></div>
                        </dl>
                        {open.changes.length === 0 ? <p className="text-sm text-ink-3">{t('log.aucun_changement')}</p> : (
                            <table className="w-full text-sm">
                                <thead><tr className="border-b border-line text-start text-xs font-bold text-ink-2 uppercase rtl:normal-case"><th className="py-2 text-start">{t('log.champ')}</th><th className="py-2 text-start">{t('log.avant')}</th><th className="py-2 text-start">{t('log.apres')}</th><th className="py-2 text-start">{t('log.difference')}</th></tr></thead>
                                <tbody>{open.changes.map((c, i) => (
                                    <tr key={i} className="border-b border-line last:border-0">
                                        <td className="py-2 pe-3 font-semibold">{c.champ}</td>
                                        <td className="py-2 pe-3 text-ink-3 line-through">{c.avant}</td>
                                        <td className="py-2 pe-3 font-bold">{c.apres}</td>
                                        <td className={`py-2 whitespace-nowrap ${c.delta?.startsWith('+') ? 'text-ok' : 'text-bad'}`}>{c.delta ?? ''}</td>
                                    </tr>
                                ))}</tbody>
                            </table>
                        )}
                    </div>
                )}
            </Dialog>
        </div>
    );
}

// ---- Paramètres -------------------------------------------------------------------

export function ParametresPage() {
    const t = useT();
    const queryClient = useQueryClient();
    const query = useQuery({ queryKey: ['/parametres'], queryFn: () => api<Settings>('/parametres') });
    const [form, setForm] = useState<Settings | null>(null);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [busy, setBusy] = useState(false);
    useEffect(() => { if (query.data && !form) setForm(query.data); }, [query.data]); // eslint-disable-line react-hooks/exhaustive-deps

    if (!form) return <div className="grid h-60 place-items-center"><span className="spinner size-6 text-brand" /></div>;
    const set = (k: keyof Settings, v: string) => setForm({ ...form, [k]: v });

    const submit = async () => {
        setBusy(true);
        setErrors({});
        try {
            setForm(await api<Settings>('/parametres', { method: 'PUT', body: form }));
            toast.success(t('spa.ui.enregistre'));
            // Company name and currency appear everywhere: reload the session words.
            void queryClient.invalidateQueries();
        } catch (e) {
            if (e instanceof ApiError && e.kind === 'validation') setErrors(fieldErrors(e.errors));
            else toast.error(errorMessage(e, t));
        } finally {
            setBusy(false);
        }
    };

    return (
        <div className="max-w-3xl">
            <PageHeader title={t('setting.plural')} />
            <Section>
                <form className="grid gap-4 p-4 sm:grid-cols-2" onSubmit={(e) => { e.preventDefault(); void submit(); }}>
                    <TextField label="Société / الشركة" value={form.societe_nom} onChange={(v) => set('societe_nom', v)} error={errors.societe_nom} />
                    <TextField label="Téléphone / الهاتف" value={form.societe_telephone} onChange={(v) => set('societe_telephone', v)} error={errors.societe_telephone} dir="ltr" />
                    <TextArea className="sm:col-span-2" label="Adresse / العنوان" value={form.societe_adresse} onChange={(v) => set('societe_adresse', v)} error={errors.societe_adresse} />
                    <TextField label="Email" type="email" value={form.societe_email} onChange={(v) => set('societe_email', v)} error={errors.societe_email} dir="ltr" />
                    <TextField label="ICE" value={form.societe_ice} onChange={(v) => set('societe_ice', v)} error={errors.societe_ice} dir="ltr" />
                    <TextField label="RC" value={form.societe_rc} onChange={(v) => set('societe_rc', v)} error={errors.societe_rc} dir="ltr" />
                    <TextField label="Devise / العملة" value={form.devise} onChange={(v) => set('devise', v)} error={errors.devise} />
                    <TextField label="TVA % / الضريبة" value={form.tva_defaut === null ? '' : String(form.tva_defaut)} onChange={(v) => set('tva_defaut', v)} error={errors.tva_defaut} inputMode="decimal" suffix="%" />
                    <div className="flex justify-end sm:col-span-2">
                        <button type="submit" className="btn btn-primary" disabled={busy}>{busy ? <span className="spinner size-4" /> : <Save />}{t('spa.ui.enregistrer')}</button>
                    </div>
                </form>
            </Section>
        </div>
    );
}
