import { useEffect, useState } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { Plus, Printer, Download, RefreshCw, ScanBarcode, AlertTriangle, ExternalLink } from 'lucide-react';
import { useT } from '@/auth/session';
import { api, ApiError, errorMessage } from '@/lib/api';
import { useListParams } from '@/lib/useListParams';
import { useDebounced } from '@/lib/useDebounced';
import { useCategories, useList, useSave } from '@/lib/queries';
import { Dialog } from '@/components/ui/Dialog';
import { toast } from '@/components/ui/toast';
import { DataTable, Pagination, type Column } from '@/components/ui/table';
import { Badge, DeleteButton, FilterSelect, Money, PageHeader, SearchBox, Toolbar } from '@/components/ui/misc';
import { SelectField, TextField, Toggle, fieldErrors } from '@/components/ui/form';
import { formatQty } from '@/lib/format';
import type { ArticleDefaults, ArticleRow } from '@/types/api';

const defaults = { q: '', categorie: '', stock: '', sort: 'designation', dir: 'asc' };

export default function ArticlesPage() {
    const t = useT();
    const [params, set] = useListParams(defaults);
    const [search, setSearch] = useState(params.q);
    const q = useDebounced(search, 300);
    useEffect(() => { if (q !== params.q) set({ q }); }, [q]); // eslint-disable-line react-hooks/exhaustive-deps

    const list = useList<ArticleRow>('/articles', params);
    const categories = useCategories();
    const [editing, setEditing] = useState<ArticleRow | 'new' | null>(null);
    const [prefill, setPrefill] = useState<Partial<ArticleRow> | null>(null);
    const [quickAdd, setQuickAdd] = useState(false);
    const queryClient = useQueryClient();

    const columns: Column<ArticleRow>[] = [
        {
            key: 'designation', header: t('article.designation'), sort: 'designation',
            cell: (a) => (
                <div className="min-w-0">
                    <p className={`font-bold ${a.actif ? '' : 'text-ink-3 line-through'}`}>{a.designation}</p>
                    <p className="text-xs text-ink-3">{a.reference}{a.code_barre ? <span className="num"> · {a.code_barre}</span> : null}</p>
                </div>
            ),
        },
        { key: 'categorie', header: t('article.categorie'), hideBelow: 'md', cell: (a) => (a.category ? <Badge>{a.category}</Badge> : <span className="text-ink-3">—</span>) },
        { key: 'prix', header: t('article.prix_vente'), sort: 'prix_vente', align: 'end', cell: (a) => <Money value={a.prix_vente} className="font-bold" /> },
        {
            key: 'stock', header: t('article.stock'), sort: 'stock', align: 'end',
            cell: (a) => <Badge tone={a.stock <= 0 ? 'bad' : a.stock <= 5 ? 'warn' : 'ok'}>{a.stock <= 0 && <AlertTriangle className="size-3" />}<span className="num">{formatQty(a.stock)}</span> {a.unite_label}</Badge>,
        },
        {
            key: 'actions', header: '', align: 'end',
            cell: (a) => (
                <div className="flex justify-end gap-1" onClick={(e) => e.stopPropagation()}>
                    {a.label_url && (
                        <a className="btn btn-ghost min-h-9 px-2.5" href={a.label_url} target="_blank" rel="noopener" title={t('article.etiquette')}>
                            <Printer />
                        </a>
                    )}
                    <DeleteButton compact label={a.designation} onConfirm={async () => {
                        try {
                            await api(`/articles/${a.id}`, { method: 'DELETE' });
                        } catch (e) {
                            toast.error(errorMessage(e, t));
                            throw e;
                        }
                        toast.success(t('spa.ui.supprime'));
                        void queryClient.invalidateQueries({ queryKey: ['/articles'] });
                    }} />
                </div>
            ),
        },
    ];

    return (
        <div>
            <PageHeader
                title={t('article.plural')}
                actions={
                    <>
                        <button className="btn btn-secondary" onClick={() => setQuickAdd(true)}>
                            <ScanBarcode />
                            {t('ajout_rapide.label')}
                        </button>
                        <button className="btn btn-primary" onClick={() => { setPrefill(null); setEditing('new'); }}>
                            <Plus />
                            {t('spa.ui.nouveau')}
                        </button>
                    </>
                }
            />

            <section className="panel overflow-hidden">
                <Toolbar>
                    <SearchBox value={search} onChange={setSearch} placeholder={t('pos.recherche')} />
                    <FilterSelect
                        label={t('article.categorie')}
                        value={params.categorie}
                        onChange={(v) => set({ categorie: v })}
                        options={[{ value: '', label: t('pos.toutes_categories') }, ...(categories.data ?? []).map((c) => ({ value: String(c.id), label: c.nom }))]}
                    />
                    <FilterSelect
                        label={t('article.stock')}
                        value={params.stock}
                        onChange={(v) => set({ stock: v })}
                        options={[{ value: '', label: `${t('article.stock')}: ${t('spa.ui.tous')}` }, { value: 'bas', label: t('spa.ui.stock_bas') }, { value: 'rupture', label: t('spa.ui.en_rupture') }]}
                    />
                </Toolbar>
                <DataTable
                    columns={columns}
                    rows={list.data?.data}
                    rowKey={(a) => a.id}
                    loading={list.isPending}
                    refreshing={list.isFetching && list.isPlaceholderData}
                    onRowClick={(a) => setEditing(a)}
                    sort={params.sort}
                    dir={params.dir as 'asc' | 'desc'}
                    onSort={(sort, dir) => set({ sort, dir })}
                />
                <Pagination page={list.data} onPage={(n) => set({ page: String(n) })} />
            </section>

            {editing && <ArticleForm article={editing === 'new' ? null : editing} prefill={prefill} onClose={() => setEditing(null)} />}
            <QuickAddDialog
                open={quickAdd}
                onClose={() => setQuickAdd(false)}
                onExisting={(a) => { setQuickAdd(false); setEditing(a); }}
                onNew={(draft) => { setQuickAdd(false); setPrefill(draft); setEditing('new'); }}
            />
        </div>
    );
}

/** GET /articles/{id} or /articles/nouveau: the same fields, partly filled. */
type FormSource = Partial<ArticleRow> & Pick<ArticleDefaults, 'unites'>;

type FormState = {
    designation: string; reference: string; code_barre: string; unite: string; prix_vente: string; prix_achat: string;
    stock: string; tva: string; actif: boolean; marque: string; category_id: string;
};

function ArticleForm({ article, prefill, onClose }: { article: ArticleRow | null; prefill: Partial<ArticleRow> | null; onClose: () => void }) {
    const t = useT();
    const queryClient = useQueryClient();
    const categories = useCategories();
    const [form, setForm] = useState<FormState | null>(null);
    const [unites, setUnites] = useState<Record<string, string>>({});
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [newCat, setNewCat] = useState<string | null>(null);

    // Defaults come from the server: next reference, a fresh EAN-13, VAT.
    const init = useQuery<FormSource>({
        queryKey: ['article-form', article?.id ?? 'new'],
        queryFn: () => api<FormSource>(article ? `/articles/${article.id}` : '/articles/nouveau'),
        gcTime: 0,
    });

    useEffect(() => {
        const d = init.data;
        if (!d || form) return;
        const src = { ...d, ...(prefill ?? {}) };
        setUnites(d.unites);
        setForm({
            designation: src.designation ?? '', reference: src.reference ?? '', code_barre: src.code_barre ?? '',
            unite: src.unite ?? 'Unite', prix_vente: src.prix_vente !== undefined ? String(src.prix_vente) : '',
            prix_achat: src.prix_achat !== undefined ? String(src.prix_achat) : '', stock: src.stock !== undefined ? String(src.stock) : '0',
            tva: src.tva !== undefined ? String(src.tva) : '0', actif: src.actif ?? true, marque: src.marque ?? '',
            category_id: src.category_id ? String(src.category_id) : '',
        });
    }, [init.data]); // eslint-disable-line react-hooks/exhaustive-deps

    const save = useSave(
        (f: FormState) => api<ArticleRow>(article ? `/articles/${article.id}` : '/articles', {
            method: article ? 'PUT' : 'POST',
            body: {
                ...f, code_barre: f.code_barre || null, marque: f.marque || null, category_id: f.category_id ? Number(f.category_id) : null,
                prix_vente: f.prix_vente === '' ? null : Number(f.prix_vente.replace(',', '.')),
                prix_achat: f.prix_achat === '' ? null : Number(f.prix_achat.replace(',', '.')),
                stock: f.stock === '' ? null : Number(f.stock.replace(',', '.')), tva: f.tva === '' ? null : Number(f.tva.replace(',', '.')),
            },
        }),
        { invalidate: ['/articles', '/categories'], onSuccess: onClose, silentErrors: true },
    );

    const submit = () => {
        if (!form) return;
        setErrors({});
        save.mutate(form, {
            onError: (e) => {
                if (e instanceof ApiError && e.kind === 'validation') setErrors(fieldErrors(e.errors));
                else toast.error(errorMessage(e, t));
            },
        });
    };

    const set = <K extends keyof FormState>(k: K, v: FormState[K]) => setForm((f) => (f ? { ...f, [k]: v } : f));
    const standard = form ? /^(\d{8}|\d{12}|\d{13}|\d{14})$/.test(form.code_barre.trim()) : true;

    return (
        <Dialog
            open
            onClose={onClose}
            size="lg"
            title={article ? article.designation : t('spa.ui.nouveau') + ' — ' + t('article.label')}
            footer={
                <>
                    <button className="btn btn-secondary" onClick={onClose}>{t('spa.ui.annuler')}</button>
                    <button className="btn btn-primary" disabled={!form || save.isPending} onClick={submit}>
                        {save.isPending && <span className="spinner size-4" />}
                        {t('spa.ui.enregistrer')}
                    </button>
                </>
            }
        >
            {!form ? (
                <div className="grid h-40 place-items-center"><span className="spinner size-6 text-brand" /></div>
            ) : (
                <form className="grid gap-4 sm:grid-cols-2" onSubmit={(e) => { e.preventDefault(); submit(); }}>
                    <TextField className="sm:col-span-2" label={t('article.designation')} value={form.designation} onChange={(v) => set('designation', v)} error={errors.designation} autoFocus required />
                    <TextField label={t('article.reference')} value={form.reference} onChange={(v) => set('reference', v)} error={errors.reference} />
                    <div>
                        <TextField
                            label={t('article.code_barre')}
                            value={form.code_barre}
                            onChange={(v) => set('code_barre', v)}
                            error={errors.code_barre}
                            hint={!standard && form.code_barre ? <span className="text-warn-ink">⚠ {t('article.code_barre_non_standard')}</span> : t('article.code_barre_aide')}
                            dir="ltr"
                        />
                        <div className="mt-1.5 flex flex-wrap gap-1.5">
                            <button type="button" className="btn btn-ghost min-h-8 px-2 text-xs" onClick={async () => set('code_barre', (await api<{ code_barre: string }>('/articles/code-barre', { method: 'POST' })).code_barre)}>
                                <RefreshCw className="size-3.5!" />
                                {t('article.code_barre_generer')}
                            </button>
                            {article?.label_url && article.code_barre === form.code_barre && (
                                <>
                                    <a className="btn btn-ghost min-h-8 px-2 text-xs" href={article.label_url} target="_blank" rel="noopener"><Printer className="size-3.5!" />{t('article.etiquette')}</a>
                                    {article.barcode_url && <a className="btn btn-ghost min-h-8 px-2 text-xs" href={article.barcode_url}><Download className="size-3.5!" />{t('article.code_barre_telecharger')}</a>}
                                </>
                            )}
                        </div>
                    </div>
                    <TextField label={t('article.prix_vente')} value={form.prix_vente} onChange={(v) => set('prix_vente', v)} error={errors.prix_vente} inputMode="decimal" suffix="DH" />
                    <TextField label={t('article.prix_achat')} value={form.prix_achat} onChange={(v) => set('prix_achat', v)} error={errors.prix_achat} inputMode="decimal" suffix="DH" />
                    <TextField label={t('article.stock')} value={form.stock} onChange={(v) => set('stock', v)} error={errors.stock} inputMode="decimal" />
                    <SelectField label={t('article.unite')} value={form.unite} onChange={(v) => set('unite', v)} error={errors.unite} options={Object.entries(unites).map(([value, label]) => ({ value, label }))} />
                    <TextField label={t('article.tva')} value={form.tva} onChange={(v) => set('tva', v)} error={errors.tva} inputMode="decimal" suffix="%" />
                    <TextField label={t('article.marque')} value={form.marque} onChange={(v) => set('marque', v)} error={errors.marque} />
                    <div className="space-y-1.5">
                        <SelectField label={t('article.categorie')} value={form.category_id} onChange={(v) => set('category_id', v)} error={errors.category_id} placeholder="—" options={(categories.data ?? []).map((c) => ({ value: String(c.id), label: c.nom }))} />
                        {newCat === null ? (
                            <button type="button" className="btn btn-ghost min-h-8 px-2 text-xs" onClick={() => setNewCat('')}><Plus className="size-3.5!" />{t('categorie.creer')}</button>
                        ) : (
                            <div className="flex gap-1.5">
                                <input className="field min-h-9" value={newCat} onChange={(e) => setNewCat(e.target.value)} placeholder={t('categorie.nom')} autoFocus />
                                <button type="button" className="btn btn-secondary min-h-9" onClick={async () => {
                                    try {
                                        const c = await api<{ id: number }>('/categories', { method: 'POST', body: { nom: newCat } });
                                        await queryClient.invalidateQueries({ queryKey: ['/categories'] });
                                        set('category_id', String(c.id));
                                        setNewCat(null);
                                    } catch (e) {
                                        toast.error(errorMessage(e, t));
                                    }
                                }}>{t('spa.ui.enregistrer')}</button>
                            </div>
                        )}
                    </div>
                    <div className="sm:col-span-2">
                        <Toggle label={t('article.actif')} checked={form.actif} onChange={(v) => set('actif', v)} />
                    </div>
                    <button type="submit" hidden />
                </form>
            )}
        </Dialog>
    );
}

/**
 * Quick add (the parallel session's flow): scan a product, see if it is
 * already an article; if not, start a new one with the barcode and, when
 * the product is known online, its name.
 */
function QuickAddDialog({ open, onClose, onExisting, onNew }: { open: boolean; onClose: () => void; onExisting: (a: ArticleRow) => void; onNew: (draft: Partial<ArticleRow>) => void }) {
    const t = useT();
    const [code, setCode] = useState('');
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => { if (open) { setCode(''); setError(null); } }, [open]);

    const lookup = async () => {
        if (!code.trim()) return;
        setBusy(true);
        setError(null);
        try {
            const r = await api<{ code_barre: string; article: ArticleRow | null; suggestion: { designation?: string; marque?: string } | null }>('/articles/lookup', { query: { code } });
            if (r.article) {
                toast.info(t('ajout_rapide.existe', { article: r.article.designation }));
                onExisting(r.article);
            } else {
                toast.info(r.suggestion ? t('ajout_rapide.trouve') : t('ajout_rapide.inconnu'));
                onNew({ code_barre: r.code_barre, designation: r.suggestion?.designation ?? '', marque: r.suggestion?.marque ?? null });
            }
        } catch (e) {
            setError(e instanceof ApiError && e.kind === 'validation' ? e.firstError() : errorMessage(e, t));
        } finally {
            setBusy(false);
        }
    };

    return (
        <Dialog open={open} onClose={onClose} size="sm" title={t('ajout_rapide.label')} description={t('ajout_rapide.aide')}>
            <form className="space-y-3" onSubmit={(e) => { e.preventDefault(); void lookup(); }}>
                <label className="relative flex items-center">
                    <ScanBarcode className="pointer-events-none absolute start-3 size-5 text-brand" />
                    <input
                        className="field min-h-12 border-2 border-brand bg-brand-soft ps-11 text-lg font-semibold"
                        value={code}
                        onChange={(e) => setCode(e.target.value)}
                        placeholder={t('ajout_rapide.scanner')}
                        data-autofocus
                        autoComplete="off"
                        dir="ltr"
                    />
                </label>
                {error && <p role="alert" className="text-sm font-medium text-bad">{error}</p>}
                <button type="submit" className="btn btn-primary w-full" disabled={busy || !code.trim()}>
                    {busy ? <span className="spinner size-4" /> : <ExternalLink />}
                    {busy ? t('ajout_rapide.recherche') : t('ajout_rapide.ajouter')}
                </button>
            </form>
        </Dialog>
    );
}
