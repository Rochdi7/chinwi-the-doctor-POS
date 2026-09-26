import { useState } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { Plus, Pencil } from 'lucide-react';
import { useT } from '@/auth/session';
import { api, ApiError, errorMessage } from '@/lib/api';
import { useCategories } from '@/lib/queries';
import { Dialog } from '@/components/ui/Dialog';
import { toast } from '@/components/ui/toast';
import { DataTable, type Column } from '@/components/ui/table';
import { Badge, DeleteButton, PageHeader } from '@/components/ui/misc';
import { TextField } from '@/components/ui/form';
import type { CategoryRow } from '@/types/api';

/** A category is only a filing label: one field. */
export default function CategoriesPage() {
    const t = useT();
    const list = useCategories();
    const queryClient = useQueryClient();
    const [editing, setEditing] = useState<CategoryRow | 'new' | null>(null);

    const refresh = () => {
        void queryClient.invalidateQueries({ queryKey: ['/categories'] });
        void queryClient.invalidateQueries({ queryKey: ['/articles'] });
    };

    const columns: Column<CategoryRow>[] = [
        { key: 'nom', header: t('categorie.nom'), cell: (c) => <b>{c.nom}</b> },
        { key: 'count', header: t('categorie.articles_count'), align: 'end', cell: (c) => <Badge>{c.articles_count}</Badge> },
        {
            key: 'actions', header: '', align: 'end',
            cell: (c) => (
                <div className="flex justify-end gap-1" onClick={(e) => e.stopPropagation()}>
                    <button className="btn btn-ghost min-h-9 px-2.5" onClick={() => setEditing(c)} title={t('spa.ui.modifier')}><Pencil /></button>
                    <DeleteButton compact label={c.nom} onConfirm={async () => {
                        try { await api(`/categories/${c.id}`, { method: 'DELETE' }); } catch (e) { toast.error(errorMessage(e, t)); throw e; }
                        toast.success(t('spa.ui.supprime'));
                        refresh();
                    }} />
                </div>
            ),
        },
    ];

    return (
        <div className="max-w-3xl">
            <PageHeader title={t('categorie.plural')} actions={<button className="btn btn-primary" onClick={() => setEditing('new')}><Plus />{t('categorie.creer')}</button>} />
            <section className="panel overflow-hidden">
                <DataTable columns={columns} rows={list.data} rowKey={(c) => c.id} loading={list.isPending} onRowClick={(c) => setEditing(c)} />
            </section>
            {editing && <CategoryForm category={editing === 'new' ? null : editing} onClose={() => setEditing(null)} onSaved={refresh} />}
        </div>
    );
}

function CategoryForm({ category, onClose, onSaved }: { category: CategoryRow | null; onClose: () => void; onSaved: () => void }) {
    const t = useT();
    const [nom, setNom] = useState(category?.nom ?? '');
    const [error, setError] = useState<string | null>(null);
    const [busy, setBusy] = useState(false);

    const submit = async () => {
        setBusy(true);
        setError(null);
        try {
            await api(category ? `/categories/${category.id}` : '/categories', { method: category ? 'PUT' : 'POST', body: { nom } });
            toast.success(t('spa.ui.enregistre'));
            onSaved();
            onClose();
        } catch (e) {
            if (e instanceof ApiError && e.kind === 'validation') setError(e.firstError());
            else toast.error(errorMessage(e, t));
        } finally {
            setBusy(false);
        }
    };

    return (
        <Dialog
            open
            onClose={onClose}
            size="sm"
            title={category ? t('categorie.modifier') : t('categorie.creer')}
            footer={<><button className="btn btn-secondary" onClick={onClose}>{t('spa.ui.annuler')}</button><button className="btn btn-primary" disabled={busy || !nom.trim()} onClick={submit}>{t('spa.ui.enregistrer')}</button></>}
        >
            <form onSubmit={(e) => { e.preventDefault(); void submit(); }}>
                <TextField label={t('categorie.nom')} value={nom} onChange={setNom} error={error} maxLength={60} data-autofocus />
            </form>
        </Dialog>
    );
}
