import { memo, useState, type RefObject } from 'react';
import { Search, X, PackageSearch } from 'lucide-react';
import { useSession, useT } from '@/auth/session';
import { formatMoney, formatQty } from '@/lib/format';
import { usePos } from '../store';
import type { Article } from '@/types/api';

interface GridProps {
    articles: Article[] | undefined;
    loading: boolean;
    refreshing: boolean;
    recherche: string;
    onRecherche: (value: string) => void;
    searchBox: RefObject<HTMLInputElement | null>;
    onAdd: (article: Article) => void;
}

const tones = [
    'bg-indigo-100 text-indigo-800',
    'bg-emerald-100 text-emerald-800',
    'bg-amber-100 text-amber-800',
    'bg-pink-100 text-pink-800',
    'bg-cyan-100 text-cyan-800',
    'bg-violet-100 text-violet-800',
];

/** Stock as the Livewire grid showed it: rupture at 0, low at 5 or less. */
function StockBadge({ stock }: { stock: number }) {
    const t = useT();

    if (stock <= 0) {
        return <span className="rounded-full bg-bad-soft px-2 py-0.5 text-[0.7rem] font-bold text-bad-ink">{t('pos.rupture')}</span>;
    }

    return (
        <span className={`rounded-full px-2 py-0.5 text-[0.7rem] font-bold ${stock <= 5 ? 'bg-warn-soft text-warn-ink' : 'bg-surface-2 text-ink-2 ring-1 ring-line'}`}>
            {t('pos.stock')} <span className="num">{formatQty(stock)}</span>
        </span>
    );
}

/**
 * One tile, one tap. Re-renders only when its own article or its quantity
 * in the cart changes, not when anything else in the cart does.
 */
const ProductTile = memo(function ProductTile({ article, devise, onAdd }: { article: Article; devise: string; onAdd: (a: Article) => void }) {
    const inCart = usePos((s) => s.lines.find((l) => l.article_id === article.id)?.quantite ?? 0);
    const [pulse, setPulse] = useState(0);

    return (
        <button
            type="button"
            title={article.designation}
            onClick={() => {
                onAdd(article);
                setPulse((p) => p + 1);
            }}
            className={`relative flex w-full flex-col gap-2 rounded-card border bg-surface p-3 text-start transition-[border-color,box-shadow,transform] duration-100 hover:border-brand hover:shadow-lift active:scale-[0.97] focus-visible:outline-3 focus-visible:outline-brand/35 ${
                inCart > 0 ? 'border-brand bg-brand-soft ring-1 ring-brand' : 'border-line'
            } ${pulse ? 'animate-pop' : ''}`}
            key={pulse}
        >
            <span className="relative block w-full">
                {article.image_url ? (
                    <span className="grid aspect-[4/3] w-full place-items-center overflow-hidden rounded-ctl bg-surface-2">
                        <img src={article.image_url} alt="" loading="lazy" decoding="async" className="size-full object-cover" />
                    </span>
                ) : (
                    <span aria-hidden className={`grid aspect-[4/3] w-full place-items-center rounded-ctl text-2xl font-extrabold ${tones[(article.category_id ?? 0) % tones.length]}`}>
                        {article.designation.trim().slice(0, 2).toUpperCase()}
                    </span>
                )}
                {inCart > 0 && (
                    <span className="num absolute top-1.5 end-1.5 rounded-full bg-brand px-2 py-0.5 text-sm font-extrabold text-white shadow-lift">× {formatQty(inCart)}</span>
                )}
            </span>
            <span className="min-w-0">
                <span className="line-clamp-2 text-[0.95rem] leading-snug font-bold text-ink">{article.designation}</span>
                <span className="mt-0.5 block truncate text-xs text-ink-3">
                    {article.reference}
                    {article.unite_label ? ` · ${article.unite_label}` : ''}
                </span>
            </span>
            <span className="mt-auto flex flex-wrap items-center justify-between gap-1.5">
                <span className={`num text-[1.05rem] font-extrabold ${article.stock <= 0 ? 'text-ink-2' : 'text-ink'}`}>{formatMoney(article.prix_vente, devise)}</span>
                <StockBadge stock={article.stock} />
            </span>
        </button>
    );
});

export function ProductGrid({ articles, loading, refreshing, recherche, onRecherche, searchBox, onAdd }: GridProps) {
    const t = useT();
    const { devise } = useSession();

    return (
        <section className="panel flex min-h-0 flex-col">
            <div className="flex items-center gap-3 border-b border-line p-3">
                <label className="relative flex flex-1 items-center">
                    <Search className="pointer-events-none absolute start-3 size-5 text-ink-3" />
                    <input
                        ref={searchBox}
                        className="field min-h-11 bg-surface-2 ps-10 pe-20 focus:bg-surface"
                        value={recherche}
                        onChange={(e) => onRecherche(e.target.value)}
                        placeholder={t('pos.recherche')}
                        aria-label={t('pos.recherche')}
                        autoComplete="off"
                        spellCheck={false}
                    />
                    <span className="absolute end-2 flex items-center gap-1">
                        {recherche !== '' ? (
                            <button type="button" className="grid size-8 place-items-center rounded-full text-ink-3 hover:bg-line hover:text-ink" onClick={() => onRecherche('')} aria-label="×">
                                <X className="size-4" />
                            </button>
                        ) : null}
                    </span>
                </label>
                <span className="hidden text-xs font-semibold whitespace-nowrap text-ink-3 sm:block">
                    {refreshing ? <span className="spinner size-4 align-middle" /> : `${articles?.length ?? 0} ${t('article.plural')}`}
                </span>
            </div>

            <div className={`min-h-0 flex-1 overflow-y-auto p-3 transition-opacity ${refreshing ? 'opacity-60' : ''}`}>
                {loading ? (
                    <div className="grid grid-cols-[repeat(auto-fill,minmax(10rem,1fr))] gap-2.5">
                        {Array.from({ length: 12 }, (_, i) => <div key={i} className="skeleton h-[13.5rem] rounded-card" />)}
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
                    <div className="grid grid-cols-[repeat(auto-fill,minmax(10rem,1fr))] gap-2.5">
                        {articles.map((a) => <ProductTile key={a.id} article={a} devise={devise} onAdd={onAdd} />)}
                    </div>
                )}
            </div>
        </section>
    );
}
