import { useT } from '@/auth/session';
import { CategoryIcon } from '@/components/CategoryIcon';
import type { Category } from '@/types/api';

interface Props {
    categories: Category[];
    active: number | null;
    onSelect: (id: number | null) => void;
}

/**
 * Category filter: a column of icon cards on a wide till, a row of pills
 * to swipe through on a tablet or a phone. Never clipped: the row keeps
 * its own height whatever the rest of the screen does.
 */
export function CategoryRail({ categories, active, onSelect }: Props) {
    const t = useT();
    const items: { id: number | null; nom: string }[] = [{ id: null, nom: t('spa.pos.tous') }, ...categories];

    return (
        <nav aria-label={t('categorie.plural')} className="-mx-3 flex flex-none snap-x gap-2 overflow-x-auto px-3 pb-0.5 max-lg:scrollbar-none lg:mx-0 lg:flex-col lg:snap-none lg:overflow-x-visible lg:overflow-y-auto lg:px-0 lg:pb-0">
            {items.map((c) => {
                const selected = active === c.id;
                return (
                    <button
                        key={c.id ?? 'all'}
                        type="button"
                        aria-pressed={selected}
                        onClick={() => onSelect(c.id)}
                        className={`group flex h-10 max-w-56 flex-none snap-start items-center gap-2 rounded-full border ps-1.5 pe-3.5 text-start transition-[background-color,border-color,box-shadow] duration-150 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand/45 focus-visible:ring-offset-2 focus-visible:ring-offset-canvas active:translate-y-px lg:h-auto lg:w-full lg:max-w-none lg:flex-col lg:items-center lg:gap-1.5 lg:rounded-xl lg:px-1.5 lg:py-2.5 lg:text-center ${
                            selected
                                ? 'border-ink bg-surface shadow-[0_0_0_1px_var(--color-ink),0_4px_12px_-4px_rgb(15_23_42/0.25)]'
                                : 'border-line bg-surface shadow-[0_1px_2px_rgb(15_23_42/0.04)] hover:border-line-strong hover:bg-surface-2'
                        }`}
                    >
                        <span className={`grid size-7 flex-none place-items-center rounded-full transition-colors lg:size-10 lg:rounded-lg ${selected ? 'bg-ink text-white' : 'bg-surface-2 text-ink-2 ring-1 ring-line group-hover:bg-surface group-hover:text-ink'}`}>
                            <CategoryIcon name={c.id === null ? null : c.nom} className="size-4 lg:size-6" strokeWidth={1.75} />
                        </span>
                        <span className={`truncate text-sm font-bold lg:line-clamp-2 lg:w-full lg:text-xs lg:leading-tight lg:whitespace-normal ${selected ? 'text-ink' : 'text-ink-2'}`}>{c.nom}</span>
                    </button>
                );
            })}
        </nav>
    );
}
