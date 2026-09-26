{{--
    Product zone of the till: search, category chips, tiles.

    The "in cart" badge on a tile is read client-side from the parent page's
    cart ($wire.$parent.panier), so this component still never re-renders
    when the cart changes.
--}}
<div
    class="pos-panel pos-products"
    x-data="{
        inCart(id) {
            const panier = $wire.$parent?.panier ?? {};
            let n = 0;
            for (const line of Object.values(panier)) {
                if (Number(line.article_id) === id) n += Number(line.quantite) || 0;
            }
            return Number(n.toFixed(2));
        },
        added(el) {
            el.classList.remove('is-added');
            void el.offsetWidth;
            el.classList.add('is-added');
        },
    }"
>
    <div class="pos-products-head">
        <div class="pos-field pos-search">
            <x-filament::icon icon="heroicon-o-magnifying-glass" />
            <input
                type="text"
                class="pos-input"
                wire:model.live.debounce.300ms="recherche"
                placeholder="{{ __('app.pos.recherche') }}"
                autocomplete="off"
            />
            @if ($recherche !== '')
                <button type="button" class="pos-search-clear" wire:click="$set('recherche', '')" title="{{ __('app.pos.retirer') }}">
                    <x-filament::icon icon="heroicon-m-x-mark" />
                </button>
            @endif
        </div>
        <span class="pos-count">{{ $this->articles->count() }} {{ __('app.article.plural') }}</span>
    </div>

    @if ($this->categories->isNotEmpty())
        <div class="pos-chips" role="tablist">
            <button
                type="button"
                role="tab"
                class="pos-chip {{ $categorie === null ? 'is-active' : '' }}"
                aria-selected="{{ $categorie === null ? 'true' : 'false' }}"
                wire:click="$set('categorie', null)"
            >
                <x-filament::icon icon="heroicon-m-squares-2x2" />
                {{ __('app.pos.tous') }}
            </button>
            @foreach ($this->categories as $cat)
                <button
                    type="button"
                    role="tab"
                    class="pos-chip {{ $categorie === $cat->id ? 'is-active' : '' }}"
                    aria-selected="{{ $categorie === $cat->id ? 'true' : 'false' }}"
                    wire:click="$set('categorie', {{ $cat->id }})"
                    wire:key="cat-{{ $cat->id }}"
                >{{ $cat->nom }}</button>
            @endforeach
        </div>
    @endif

    <div class="pos-products-body" wire:loading.class="is-loading" wire:target="recherche,categorie">
        @if ($this->articles->isEmpty())
            <div class="pos-state">
                <span class="pos-state-icon"><x-filament::icon icon="heroicon-o-magnifying-glass" /></span>
                <strong>{{ __('app.pos.aucun_article') }}</strong>
                @if ($recherche !== '')
                    <span class="pos-state-term">« {{ $recherche }} »</span>
                @endif
            </div>
        @else
            <div class="pos-grid">
                @foreach ($this->articles as $article)
                    @php
                        $stock = (float) $article->stock;
                        $stockTxt = rtrim(rtrim(number_format($stock, 2, '.', ''), '0'), '.');
                        $etat = $stock <= 0 ? 'out' : ($stock <= 5 ? 'low' : 'ok');
                        $initiales = mb_strtoupper(mb_substr(trim($article->designation), 0, 2));
                    @endphp
                    {{-- $parent: the tap goes to the page component in one request,
                         without this grid being re-rendered. --}}
                    <button
                        type="button"
                        class="pos-tile is-{{ $etat }}"
                        :class="inCart({{ $article->id }}) > 0 && 'is-in-cart'"
                        wire:click="$parent.ajouter({{ $article->id }})"
                        x-on:click="added($el)"
                        wire:key="tile-{{ $article->id }}"
                        title="{{ $article->designation }}"
                    >
                        <span class="pos-tile-thumb tone-{{ ($article->category_id ?? 0) % 6 }}" aria-hidden="true">{{ $initiales }}</span>
                        <span class="pos-tile-qty" x-show="inCart({{ $article->id }}) > 0" x-text="'× ' + inCart({{ $article->id }})" x-cloak></span>
                        <span class="pos-tile-body">
                            <span class="pos-tile-name">{{ $article->designation }}</span>
                            <span class="pos-tile-ref">{{ $article->reference }}@if ($article->unite) · {{ \App\Support\Units::label($article->unite) }}@endif</span>
                            <span class="pos-tile-foot">
                                <span class="pos-tile-price pos-num">{{ \App\Support\Money::format($article->prix_vente) }}</span>
                                @if ($etat === 'out')
                                    <span class="pos-stock out">{{ __('app.pos.rupture') }}</span>
                                @else
                                    <span class="pos-stock {{ $etat }}">{{ __('app.pos.stock') }} {{ $stockTxt }}</span>
                                @endif
                            </span>
                        </span>
                    </button>
                @endforeach
            </div>
        @endif
    </div>
</div>
