import { LayoutGrid } from 'lucide-react';
import { useT } from '@/auth/session';
import type { Category } from '@/types/api';

interface Props {
    categories: Category[];
    active: number | null;
    onSelect: (id: number | null) => void;
}

/**
 * Category filter. A slim column on a wide till, a row of chips when the
 * screen is narrower.
 */
export function CategoryRail({ categories, active, onSelect }: Props) {
    const t = useT();
    const items: { id: number | null; nom: string }[] = [{ id: null, nom: t('spa.pos.tous') }, ...categories];

    return (
        <nav aria-label={t('categorie.plural')} className="flex gap-1.5 overflow-x-auto pb-1 lg:flex-col lg:overflow-x-visible lg:overflow-y-auto lg:pb-0">
            {items.map((c) => {
                const selected = active === c.id;
                return (
                    <button
                        key={c.id ?? 'all'}
                        type="button"
                        aria-pressed={selected}
                        onClick={() => onSelect(c.id)}
                        className={`flex min-h-11 flex-none items-center gap-2 rounded-ctl px-3 text-start text-sm font-semibold transition-colors lg:w-full ${
                            selected
                                ? 'bg-navy text-white shadow-card'
                                : 'bg-surface text-ink-2 ring-1 ring-line hover:bg-surface-2 hover:text-ink lg:bg-transparent lg:ring-0'
                        }`}
                    >
                        {c.id === null && <LayoutGrid className="size-4 flex-none" />}
                        <span className="truncate">{c.nom}</span>
                    </button>
                );
            })}
        </nav>
    );
}
