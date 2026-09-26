import type { ReactNode } from 'react';
import { ArrowDown, ArrowUp, ChevronLeft, ChevronRight, SearchX } from 'lucide-react';
import { useT } from '@/auth/session';
import type { Paginated } from '@/types/api';

export interface Column<T> {
    key: string;
    header: ReactNode;
    cell: (row: T) => ReactNode;
    /** Server-side sort key; the column header becomes a sort button. */
    sort?: string;
    align?: 'start' | 'end' | 'center';
    className?: string;
    /** Hidden below this breakpoint to keep small screens readable. */
    hideBelow?: 'sm' | 'md' | 'lg';
}

interface Props<T> {
    columns: Column<T>[];
    rows: T[] | undefined;
    rowKey: (row: T) => string | number;
    loading?: boolean;
    refreshing?: boolean;
    onRowClick?: (row: T) => void;
    sort?: string;
    dir?: 'asc' | 'desc';
    onSort?: (key: string, dir: 'asc' | 'desc') => void;
    empty?: ReactNode;
}

const hide = { sm: 'hidden sm:table-cell', md: 'hidden md:table-cell', lg: 'hidden lg:table-cell' } as const;
const align = { start: 'text-start', end: 'text-end', center: 'text-center' } as const;

/**
 * A plain, fast table: sortable headers, a skeleton on first load, dimmed
 * rows while a new page loads, a clear empty state. Rows open on click.
 */
export function DataTable<T>({ columns, rows, rowKey, loading, refreshing, onRowClick, sort, dir, onSort, empty }: Props<T>) {
    const t = useT();

    return (
        <div className="overflow-x-auto">
            <table className="w-full border-separate border-spacing-0 text-sm">
                <thead>
                    <tr>
                        {columns.map((c) => {
                            const active = c.sort && c.sort === sort;
                            return (
                                <th
                                    key={c.key}
                                    scope="col"
                                    aria-sort={active ? (dir === 'asc' ? 'ascending' : 'descending') : undefined}
                                    className={`sticky top-0 z-10 border-b border-line bg-surface-2 px-4 py-2.5 text-xs font-bold tracking-wide whitespace-nowrap text-ink-2 uppercase rtl:tracking-normal rtl:normal-case ${align[c.align ?? 'start']} ${c.hideBelow ? hide[c.hideBelow] : ''}`}
                                >
                                    {c.sort && onSort ? (
                                        <button
                                            type="button"
                                            className="inline-flex items-center gap-1 hover:text-ink"
                                            onClick={() => onSort(c.sort!, active && dir === 'asc' ? 'desc' : 'asc')}
                                        >
                                            {c.header}
                                            {active && (dir === 'asc' ? <ArrowUp className="size-3.5" /> : <ArrowDown className="size-3.5" />)}
                                        </button>
                                    ) : (
                                        c.header
                                    )}
                                </th>
                            );
                        })}
                    </tr>
                </thead>
                <tbody className={`transition-opacity ${refreshing ? 'opacity-60' : ''}`}>
                    {loading
                        ? Array.from({ length: 8 }, (_, i) => (
                              <tr key={i}>
                                  {columns.map((c) => (
                                      <td key={c.key} className={`border-b border-line px-4 py-3 ${c.hideBelow ? hide[c.hideBelow] : ''}`}>
                                          <span className="skeleton block h-4 rounded" />
                                      </td>
                                  ))}
                              </tr>
                          ))
                        : rows?.map((row) => (
                              <tr
                                  key={rowKey(row)}
                                  onClick={onRowClick ? () => onRowClick(row) : undefined}
                                  className={onRowClick ? 'cursor-pointer hover:bg-brand-soft/50' : 'hover:bg-surface-2'}
                              >
                                  {columns.map((c) => (
                                      <td key={c.key} className={`border-b border-line px-4 py-3 align-middle ${align[c.align ?? 'start']} ${c.hideBelow ? hide[c.hideBelow] : ''} ${c.className ?? ''}`}>
                                          {c.cell(row)}
                                      </td>
                                  ))}
                              </tr>
                          ))}
                </tbody>
            </table>
            {!loading && rows?.length === 0 && (
                empty ?? (
                    <div className="flex flex-col items-center gap-2 px-6 py-14 text-center text-ink-2">
                        <span className="grid size-12 place-items-center rounded-full border border-dashed border-line-strong bg-surface-2">
                            <SearchX className="size-6 text-ink-3" />
                        </span>
                        <p className="font-bold text-ink">{t('spa.ui.aucun_resultat')}</p>
                        <p className="text-sm">{t('spa.ui.aucun_resultat_aide')}</p>
                    </div>
                )
            )}
        </div>
    );
}

/** Page x of y, with previous / next. */
export function Pagination({ page, onPage }: { page: Paginated<unknown> | undefined; onPage: (n: number) => void }) {
    const t = useT();
    if (!page || page.last_page <= 1) {
        return page ? <p className="px-4 py-3 text-xs text-ink-3">{t('spa.ui.resultats', { count: page.total })}</p> : null;
    }

    return (
        <div className="flex flex-wrap items-center justify-between gap-2 px-4 py-3">
            <p className="text-xs text-ink-3">
                {t('spa.ui.resultats', { count: page.total })} · {t('spa.ui.page', { page: page.current_page, pages: page.last_page })}
            </p>
            <div className="flex gap-1.5">
                <button className="btn btn-secondary min-h-9 px-3 text-sm" disabled={page.current_page <= 1} onClick={() => onPage(page.current_page - 1)}>
                    <ChevronLeft className="rtl:-scale-x-100" />
                    {t('spa.ui.precedent')}
                </button>
                <button className="btn btn-secondary min-h-9 px-3 text-sm" disabled={page.current_page >= page.last_page} onClick={() => onPage(page.current_page + 1)}>
                    {t('spa.ui.suivant')}
                    <ChevronRight className="rtl:-scale-x-100" />
                </button>
            </div>
        </div>
    );
}
