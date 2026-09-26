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
                        className={`group flex w-24 flex-none flex-col items-center gap-1.5 rounded-xl border px-1.5 py-2.5 text-center transition-[background-color,border-color,box-shadow] duration-150 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand/45 focus-visible:ring-offset-2 focus-visible:ring-offset-canvas active:translate-y-px lg:w-full ${
                            selected
                                ? 'border-ink bg-surface shadow-[0_0_0_1px_var(--color-ink),0_4px_12px_-4px_rgb(15_23_42/0.25)]'
                                : 'border-line bg-surface shadow-[0_1px_2px_rgb(15_23_42/0.04)] hover:border-line-strong hover:bg-surface-2'
                        }`}
                    >
                        <span className={`grid size-10 place-items-center rounded-lg transition-colors ${selected ? 'bg-ink text-white' : 'bg-surface-2 text-ink-2 ring-1 ring-line group-hover:bg-surface group-hover:text-ink'}`}>
                            <CategoryIcon name={c.id === null ? null : c.nom} className="size-6" strokeWidth={1.75} />
                        </span>
                        <span className={`line-clamp-2 w-full text-xs leading-tight font-bold ${selected ? 'text-ink' : 'text-ink-2'}`}>{c.nom}</span>
                    </button>
                );
            })}
        </nav>
    );
}
