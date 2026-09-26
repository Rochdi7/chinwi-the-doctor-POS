import { memo, useMemo, useState, type RefObject } from 'react';
import { Search, X, PackageSearch, Check, Minus, Plus } from 'lucide-react';
import { useSession, useT } from '@/auth/session';
import { ProductPhoto } from '@/components/ProductPhoto';
import { formatMoney, formatQty } from '@/lib/format';
import { usePos } from '../store';
import type { Article, Category } from '@/types/api';

interface GridProps {
    articles: Article[] | undefined;
    categories: Category[];
    loading: boolean;
    refreshing: boolean;
    recherche: string;
    onRecherche: (value: string) => void;
    searchBox: RefObject<HTMLInputElement | null>;
    onAdd: (article: Article) => void;
}

/** Stock as the Livewire grid showed it: rupture at 0, low at 5 or less. */
function StockBadge({ stock }: { stock: number }) {
    const t = useT();

    if (stock <= 0) {
        return <span className="rounded-full bg-bad-soft px-2 py-0.5 text-[0.7rem] font-bold text-bad-ink">{t('pos.rupture')}</span>;
    }

    return (
        <span className={`rounded-full px-2 py-0.5 text-[0.7rem] font-bold ${stock <= 5 ? 'bg-warn-soft text-warn-ink' : 'bg-surface/90 text-ink-2 ring-1 ring-line'}`}>
            {t('pos.stock')} <span className="num">{formatQty(stock)}</span>
        </span>
    );
}

const stepBtn = 'grid size-7 flex-none place-items-center rounded-md border border-line-strong bg-surface text-ink shadow-[0_1px_2px_rgb(15_23_42/0.05)] transition-colors hover:border-ink-3 hover:bg-surface-2 active:translate-y-px focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand/45 disabled:border-line disabled:bg-surface-2 disabled:text-ink-3 disabled:shadow-none';

/**
 * One product card: photo, category, name, price and how many are in the
 * cart. A tap on the photo adds one, as does +. Re-renders only when its own
 * article or its quantity in the cart changes, not when anything else in the
 * cart does.
 */
const ProductTile = memo(function ProductTile({ article, categorie, devise, onAdd }: { article: Article; categorie: string | undefined; devise: string; onAdd: (a: Article) => void }) {
    const t = useT();
    const inCart = usePos((s) => s.lines.find((l) => l.article_id === article.id)?.quantite ?? 0);
    const [pulse, setPulse] = useState(0);

    const add = () => {
        onAdd(article);
        setPulse((p) => p + 1);
    };

    return (
        <div
            className={`relative flex flex-col rounded-2xl border bg-surface p-2.5 transition-[border-color,box-shadow] duration-100 hover:shadow-lift sm:p-3 ${
                inCart > 0 ? 'border-ok ring-1 ring-ok' : 'border-line hover:border-line-strong'
            }`}
        >
            {inCart > 0 && (
                <span className="absolute top-2 end-2 z-10 grid size-6 place-items-center rounded-full bg-ok text-white shadow-card" aria-hidden>
                    <Check className="size-3.5" strokeWidth={3} />
                </span>
            )}

            {/* The big tap target: the photo and the name both add one. */}
            <button type="button" title={article.designation} onClick={add} className={`group flex flex-col text-start focus-visible:outline-3 focus-visible:outline-brand/35 ${pulse ? 'animate-pop' : ''}`} key={pulse}>
                <span className="relative block w-full">
                    <ProductPhoto src={article.image_url} className="aspect-square w-full transition-transform duration-150 group-hover:scale-[1.02]" iconClassName="size-10" />
                    <span className="absolute bottom-1.5 start-1.5"><StockBadge stock={article.stock} /></span>
                </span>
                <span className="mt-2.5 block truncate text-xs text-ink-3">{categorie ?? article.unite_label}</span>
                <span className="line-clamp-2 text-sm leading-snug font-bold text-ink sm:text-[0.95rem]">{article.designation}</span>
            </button>

            <span className="mt-2 flex items-center justify-between gap-1.5 border-t border-dashed border-line pt-2">
                <span className={`num min-w-0 truncate text-sm font-extrabold sm:text-[0.95rem] ${article.stock <= 0 ? 'text-ink-2' : 'text-ink'}`}>{formatMoney(article.prix_vente, devise)}</span>
                <span className="flex flex-none items-center gap-0.5" dir="ltr">
                    <button type="button" className={stepBtn} disabled={inCart <= 0} onClick={() => usePos.getState().moins(article.id)} title={t('pos.moins')} aria-label={t('pos.moins')}>
                        <Minus className="size-3.5" />
                    </button>
                    <span className="num min-w-5 text-center text-sm font-extrabold">{formatQty(inCart)}</span>
                    <button type="button" className={stepBtn} onClick={add} title={t('pos.plus')} aria-label={t('pos.plus')}>
                        <Plus className="size-3.5" />
                    </button>
                </span>
            </span>
        </div>
    );
});

export function ProductGrid({ articles, categories, loading, refreshing, recherche, onRecherche, searchBox, onAdd }: GridProps) {
    const t = useT();
    const { devise, user, locale } = useSession();
    const names = useMemo(() => new Map(categories.map((c) => [c.id, c.nom])), [categories]);
    const today = useMemo(() => new Intl.DateTimeFormat(locale === 'ary' ? 'ar-MA' : locale, { dateStyle: 'long' }).format(new Date()), [locale]);

    return (
        <section className="flex min-h-0 flex-1 flex-col">
            <div className="flex flex-wrap items-center gap-2 pb-2 sm:gap-3 sm:pb-3">
                {/* On a phone the search box takes the row: the greeting is a nicety. */}
                <div className="hidden min-w-0 flex-1 sm:block">
                    <h1 className="truncate text-lg font-extrabold tracking-tight md:text-xl">{t('spa.pos.bienvenue', { name: user?.name ?? '' })}</h1>
                    <p className="text-sm text-ink-2">{today}</p>
                </div>
                <label className="relative flex min-w-0 flex-1 items-center sm:w-72 sm:flex-none">
                    <Search className="pointer-events-none absolute start-3 size-5 text-ink-3" />
                    <input
                        ref={searchBox}
                        className="field min-h-11 rounded-xl bg-surface ps-10 pe-10 shadow-card"
                        value={recherche}
                        onChange={(e) => onRecherche(e.target.value)}
                        placeholder={t('pos.recherche')}
                        aria-label={t('pos.recherche')}
                        autoComplete="off"
                        spellCheck={false}
                    />
                    {recherche !== '' && (
                        <button type="button" className="absolute end-2 grid size-8 place-items-center rounded-full text-ink-3 hover:bg-line hover:text-ink" onClick={() => onRecherche('')} aria-label={t('spa.pos.effacer')}>
                            <X className="size-4" />
                        </button>
                    )}
                </label>
                <span className="hidden h-11 items-center rounded-xl border border-line bg-surface px-3 text-xs font-semibold whitespace-nowrap text-ink-2 shadow-[0_1px_2px_rgb(15_23_42/0.04)] md:inline-flex">
                    {refreshing ? <span className="spinner size-4 align-middle" /> : `${articles?.length ?? 0} ${t('article.plural')}`}
                </span>
            </div>

            <div className={`min-h-0 flex-1 overflow-y-auto overscroll-contain pb-2 transition-opacity ${refreshing ? 'opacity-60' : ''}`}>
                {loading ? (
                    <div className="grid grid-cols-[repeat(auto-fill,minmax(9.5rem,1fr))] gap-2 sm:grid-cols-[repeat(auto-fill,minmax(11rem,1fr))] sm:gap-3">
                        {Array.from({ length: 8 }, (_, i) => <div key={i} className="skeleton h-72 rounded-2xl" />)}
                    </div>
                ) : !articles || articles.length === 0 ? (
                    <div className="flex h-full min-h-60 flex-col items-center justify-center gap-2 text-center text-ink-2">
                        <span className="grid size-14 place-items-center rounded-full border border-dashed border-line-strong bg-surface-2">
                            <PackageSearch className="size-7 text-ink-3" />
                        </span>
                        <p className="font-bold text-ink">{t('pos.aucun_article')}</p>
                        {recherche.trim() !== '' && <p className="text-sm break-all">« {recherche.trim()} »</p>}
                    </div>
                ) : (
                    <div className="grid grid-cols-[repeat(auto-fill,minmax(9.5rem,1fr))] gap-2 sm:grid-cols-[repeat(auto-fill,minmax(11rem,1fr))] sm:gap-3 xl:grid-cols-[repeat(auto-fill,minmax(12rem,1fr))]">
                        {articles.map((a) => <ProductTile key={a.id} article={a} categorie={a.category_id === null ? undefined : names.get(a.category_id)} devise={devise} onAdd={onAdd} />)}
                    </div>
                )}
            </div>
        </section>
    );
}
