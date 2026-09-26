import { useEffect, useState } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { Plus, Pencil, ArrowLeft, Phone, Mail, MapPin, Printer } from 'lucide-react';
import { useT } from '@/auth/session';
import { api, ApiError, errorMessage } from '@/lib/api';
import { useListParams } from '@/lib/useListParams';
import { useDebounced } from '@/lib/useDebounced';
import { useList } from '@/lib/queries';
import { Dialog } from '@/components/ui/Dialog';
import { toast } from '@/components/ui/toast';
import { DataTable, Pagination, type Column } from '@/components/ui/table';
import { Badge, DateText, DeleteButton, FilterSelect, ModeBadge, Money, PageHeader, SearchBox, Section, StatutBadge, Toolbar } from '@/components/ui/misc';
import { TextArea, TextField, Toggle, fieldErrors } from '@/components/ui/form';
import type { ClientDetail, ClientRow } from '@/types/api';

const defaults = { q: '', doit: '', sort: 'raison_sociale', dir: 'asc' };

export default function ClientsPage() {
    const t = useT();
    const navigate = useNavigate();
    const [params, set] = useListParams(defaults);
    const [search, setSearch] = useState(params.q);
    const q = useDebounced(search, 300);
    useEffect(() => { if (q !== params.q) set({ q }); }, [q]); // eslint-disable-line react-hooks/exhaustive-deps

    const list = useList<ClientRow>('/clients', params);
    const [creating, setCreating] = useState(false);

    const columns: Column<ClientRow>[] = [
        { key: 'nom', header: t('client.raison_sociale'), sort: 'raison_sociale', cell: (c) => <div><p className={`font-bold ${c.actif ? '' : 'text-ink-3'}`}>{c.raison_sociale}</p>{c.ice && <p className="text-xs text-ink-3">{t('client.ice')} <span className="num">{c.ice}</span></p>}</div> },
        { key: 'tel', header: t('client.telephone'), hideBelow: 'sm', cell: (c) => <span className="num">{c.telephone ?? '—'}</span> },
        { key: 'solde', header: t('client.solde'), sort: 'solde', align: 'end', cell: (c) => <Money value={c.solde} className={`font-bold ${c.solde > 0 ? 'text-bad' : 'text-ok'}`} /> },
        { key: 'actif', header: t('client.actif'), align: 'center', hideBelow: 'md', cell: (c) => <Badge tone={c.actif ? 'ok' : 'neutral'}>{c.actif ? t('spa.ui.actif') : t('spa.ui.inactif')}</Badge> },
    ];

    return (
        <div>
            <PageHeader title={t('client.plural')} actions={<button className="btn btn-primary" onClick={() => setCreating(true)}><Plus />{t('spa.ui.nouveau')}</button>} />
            <section className="panel overflow-hidden">
                <Toolbar>
                    <SearchBox value={search} onChange={setSearch} />
                    <FilterSelect label={t('client.solde')} value={params.doit} onChange={(v) => set({ doit: v })} options={[{ value: '', label: t('spa.ui.tous') }, { value: '1', label: t('spa.ui.doivent') }]} />
                </Toolbar>
                <DataTable
                    columns={columns} rows={list.data?.data} rowKey={(c) => c.id} loading={list.isPending} refreshing={list.isFetching && list.isPlaceholderData}
                    onRowClick={(c) => navigate(`/clients/${c.id}`)} sort={params.sort} dir={params.dir as 'asc' | 'desc'} onSort={(sort, dir) => set({ sort, dir })}
                />
                <Pagination page={list.data} onPage={(n) => set({ page: String(n) })} />
            </section>
            {creating && <ClientForm client={null} onClose={() => setCreating(false)} onSaved={(c) => navigate(`/clients/${c.id}`)} />}
        </div>
    );
}

/** One client: contact, balance, their sales and payments. */
export function ClientDetailPage() {
    const t = useT();
    const { id } = useParams();
    const navigate = useNavigate();
    const queryClient = useQueryClient();
    const [editing, setEditing] = useState(false);
    const query = useQuery({ queryKey: ['/clients', id], queryFn: () => api<ClientDetail>(`/clients/${id}`) });
    const c = query.data;

    if (!c) return <div className="grid h-60 place-items-center"><span className="spinner size-6 text-brand" /></div>;

    return (
        <div className="space-y-4">
            <Link to="/clients" className="btn btn-ghost -ms-2 min-h-9 px-2 text-sm"><ArrowLeft className="rtl:-scale-x-100" />{t('client.plural')}</Link>
            <PageHeader
                title={c.raison_sociale}
                subtitle={c.actif ? undefined : t('spa.ui.inactif')}
                actions={
                    <>
                        <button className="btn btn-secondary" onClick={() => setEditing(true)}><Pencil />{t('spa.ui.modifier')}</button>
                        <DeleteButton label={c.raison_sociale} onConfirm={async () => {
                            try { await api(`/clients/${c.id}`, { method: 'DELETE' }); } catch (e) { toast.error(errorMessage(e, t)); throw e; }
                            toast.success(t('spa.ui.supprime'));
                            void queryClient.invalidateQueries({ queryKey: ['/clients'] });
                            navigate('/clients');
                        }} />
                    </>
                }
            />

            <div className="grid gap-3 lg:grid-cols-3">
                <Section title={t('spa.ui.informations')}>
                    <dl className="space-y-3 p-4 text-sm">
                        <div className="flex items-center justify-between rounded-ctl bg-surface-2 p-3">
                            <dt className="font-semibold text-ink-2">{t('client.solde')}</dt>
                            <dd><Money value={c.solde} className={`text-xl font-extrabold ${c.solde > 0 ? 'text-bad' : 'text-ok'}`} /></dd>
                        </div>
                        {c.telephone && <div className="flex items-center gap-2"><Phone className="size-4 text-ink-3" /><span className="num">{c.telephone}</span></div>}
                        {c.email && <div className="flex items-center gap-2"><Mail className="size-4 text-ink-3" /><bdi dir="ltr">{c.email}</bdi></div>}
                        {c.adresse && <div className="flex items-start gap-2"><MapPin className="mt-0.5 size-4 text-ink-3" />{c.adresse}</div>}
                        {(c.ice || c.rc || c.numero_compte) && (
                            <div className="grid grid-cols-3 gap-2 border-t border-line pt-3 text-xs">
                                <div><dt className="text-ink-3">{t('client.ice')}</dt><dd className="font-semibold"><span className="num">{c.ice ?? '—'}</span></dd></div>
                                <div><dt className="text-ink-3">{t('client.rc')}</dt><dd className="font-semibold"><span className="num">{c.rc ?? '—'}</span></dd></div>
                                <div><dt className="text-ink-3">{t('client.numero_compte')}</dt><dd className="font-semibold"><span className="num">{c.numero_compte ?? '—'}</span></dd></div>
                            </div>
                        )}
                    </dl>
                </Section>

                <Section title={t('invoice.plural')} className="overflow-hidden lg:col-span-2">
                    <DataTable
                        columns={[
                            { key: 'n', header: t('invoice.numero'), cell: (i) => <b>{i.numero}</b> },
                            { key: 'd', header: t('invoice.date_facture'), cell: (i) => <DateText value={i.date} /> },
                            { key: 's', header: t('invoice.statut'), cell: (i) => <StatutBadge statut={i.statut} /> },
                            { key: 't', header: t('invoice.total_ttc'), align: 'end', cell: (i) => <Money value={i.total_ttc} /> },
                            { key: 'r', header: t('invoice.reste'), align: 'end', cell: (i) => <Money value={i.reste} className={i.reste > 0 ? 'font-bold text-bad' : ''} /> },
                        ]}
                        rows={c.invoices}
                        rowKey={(i) => i.id}
                        onRowClick={(i) => navigate(`/ventes/${i.id}`)}
                    />
                </Section>
            </div>

            <Section title={t('payment.plural')} className="overflow-hidden">
                <DataTable
                    columns={[
                        { key: 'd', header: t('payment.date_paiement'), cell: (p) => <DateText value={p.date} /> },
                        { key: 'n', header: t('payment.invoice'), cell: (p) => p.numero ?? '—' },
                        { key: 'm', header: t('payment.mode'), cell: (p) => <ModeBadge mode={p.mode} /> },
                        { key: 'x', header: t('payment.montant'), align: 'end', cell: (p) => <Money value={p.montant} className="font-bold" /> },
                        { key: 'r', header: '', align: 'end', cell: (p) => <a className="btn btn-ghost min-h-9 px-2.5" href={p.pdf_url} target="_blank" rel="noopener" title={t('receipt.print')} onClick={(e) => e.stopPropagation()}><Printer /></a> },
                    ]}
                    rows={c.payments}
                    rowKey={(p) => p.id}
                />
            </Section>

            {editing && <ClientForm client={c} onClose={() => setEditing(false)} onSaved={() => void queryClient.invalidateQueries({ queryKey: ['/clients'] })} />}
        </div>
    );
}

type ClientForm = Pick<ClientRow, 'raison_sociale' | 'telephone' | 'email' | 'adresse' | 'ice' | 'rc' | 'numero_compte' | 'actif'>;

export function ClientForm({ client, onClose, onSaved }: { client: ClientRow | null; onClose: () => void; onSaved: (c: ClientRow) => void }) {
    const t = useT();
    const [form, setForm] = useState<ClientForm>({
        raison_sociale: client?.raison_sociale ?? '', telephone: client?.telephone ?? '', email: client?.email ?? '', adresse: client?.adresse ?? '',
        ice: client?.ice ?? '', rc: client?.rc ?? '', numero_compte: client?.numero_compte ?? '', actif: client?.actif ?? true,
    });
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [busy, setBusy] = useState(false);
    const set = <K extends keyof ClientForm>(k: K, v: ClientForm[K]) => setForm((f) => ({ ...f, [k]: v }));

    const submit = async () => {
        setBusy(true);
        setErrors({});
        const body = Object.fromEntries(Object.entries(form).map(([k, v]) => [k, v === '' ? null : v]));
        try {
            const saved = await api<ClientRow>(client ? `/clients/${client.id}` : '/clients', { method: client ? 'PUT' : 'POST', body });
            toast.success(t('spa.ui.enregistre'));
            onSaved(saved);
            onClose();
        } catch (e) {
            if (e instanceof ApiError && e.kind === 'validation') setErrors(fieldErrors(e.errors));
            else toast.error(errorMessage(e, t));
        } finally {
            setBusy(false);
        }
    };

    return (
        <Dialog
            open
            onClose={onClose}
            size="lg"
            title={client ? client.raison_sociale : `${t('spa.ui.nouveau')} — ${t('client.label')}`}
            footer={<><button className="btn btn-secondary" onClick={onClose}>{t('spa.ui.annuler')}</button><button className="btn btn-primary" disabled={busy} onClick={submit}>{busy && <span className="spinner size-4" />}{t('spa.ui.enregistrer')}</button></>}
        >
            <form className="grid gap-4 sm:grid-cols-2" onSubmit={(e) => { e.preventDefault(); void submit(); }}>
                <TextField className="sm:col-span-2" label={t('client.raison_sociale')} value={form.raison_sociale} onChange={(v) => set('raison_sociale', v)} error={errors.raison_sociale} data-autofocus />
                <TextField label={t('client.telephone')} value={form.telephone} onChange={(v) => set('telephone', v)} error={errors.telephone} type="tel" dir="ltr" />
                <TextField label={t('client.email')} value={form.email} onChange={(v) => set('email', v)} error={errors.email} type="email" dir="ltr" />
                <TextArea className="sm:col-span-2" label={t('client.adresse')} value={form.adresse} onChange={(v) => set('adresse', v)} error={errors.adresse} />
                <TextField label={t('client.ice')} value={form.ice} onChange={(v) => set('ice', v)} error={errors.ice} dir="ltr" />
                <TextField label={t('client.rc')} value={form.rc} onChange={(v) => set('rc', v)} error={errors.rc} dir="ltr" />
                <TextField label={t('client.numero_compte')} value={form.numero_compte} onChange={(v) => set('numero_compte', v)} error={errors.numero_compte} dir="ltr" />
                <div className="sm:col-span-2"><Toggle label={t('client.actif')} checked={form.actif} onChange={(v) => set('actif', v)} /></div>
                <button type="submit" hidden />
            </form>
        </Dialog>
    );
}
