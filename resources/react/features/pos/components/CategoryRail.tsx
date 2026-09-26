import { useT } from '@/auth/session';
import { CategoryIcon } from '@/components/CategoryIcon';
import type { Category } from '@/types/api';

interface Props {
    categories: Category[];
    active: number | null;
    onSelect: (id: number | null) => void;
}

/**
 * Category filter: a column of icon cards on a wide till, a row of the
 * same cards to swipe through when the screen is narrower.
 */
export function CategoryRail({ categories, active, onSelect }: Props) {
    const t = useT();
    const items: { id: number | null; nom: string }[] = [{ id: null, nom: t('spa.pos.tous') }, ...categories];

    return (
        <nav aria-label={t('categorie.plural')} className="flex gap-2 overflow-x-auto pb-1 lg:flex-col lg:overflow-x-visible lg:overflow-y-auto lg:pb-0">
            {items.map((c) => {
                const selected = active === c.id;
                return (
                    <button
                        key={c.id ?? 'all'}
                        type="button"
                        aria-pressed={selected}
                        onClick={() => onSelect(c.id)}
                        className={`flex w-24 flex-none flex-col items-center gap-1.5 rounded-2xl border bg-surface px-1.5 py-3 text-center transition-[border-color,box-shadow,transform] duration-100 active:scale-[0.97] lg:w-full ${
                            selected ? 'border-warm shadow-card ring-1 ring-warm' : 'border-line hover:border-line-strong hover:shadow-card'
                        }`}
                    >
                        <span className={`grid size-11 place-items-center rounded-xl ${selected ? 'bg-warm-soft text-warm' : 'bg-surface-2 text-ink-2'}`}>
                            <CategoryIcon name={c.id === null ? null : c.nom} className="size-6" strokeWidth={1.75} />
                        </span>
                        <span className={`line-clamp-2 w-full text-xs leading-tight font-bold ${selected ? 'text-ink' : 'text-ink-2'}`}>{c.nom}</span>
                    </button>
                );
            })}
        </nav>
    );
}
