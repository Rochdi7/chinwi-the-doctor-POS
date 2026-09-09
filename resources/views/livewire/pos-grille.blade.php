<div class="pos-card">
    <div class="pos-toolbar">
        <div class="pos-field pos-search">
            <x-filament::icon icon="heroicon-o-magnifying-glass" />
            <input
                type="text"
                class="pos-input"
                wire:model.live.debounce.300ms="recherche"
                placeholder="{{ __('app.pos.recherche') }}"
                autocomplete="off"
            />
        </div>
        <div class="pos-field pos-cat">
            <x-filament::icon icon="heroicon-o-tag" />
            <select class="pos-input" wire:model.live="categorie">
                <option value="">{{ __('app.pos.toutes_categories') }}</option>
                @foreach ($this->categories as $cat)
                    <option value="{{ $cat->id }}">{{ $cat->nom }}</option>
                @endforeach
            </select>
        </div>
        <span class="pos-count">{{ $this->articles->count() }} {{ __('app.article.plural') }}</span>
    </div>

    @if ($this->articles->isEmpty())
        <div class="pos-empty">
            <x-filament::icon icon="heroicon-o-magnifying-glass" />
            <span>{{ __('app.pos.aucun_article') }}</span>
        </div>
    @else
        <div class="pos-grid">
            @foreach ($this->articles as $article)
                @php($stock = (float) $article->stock)
                {{-- $parent: the tap goes to the page component in one request,
                     without this grid being re-rendered. --}}
                <button
                    type="button"
                    class="pos-tile"
                    wire:click="$parent.ajouter({{ $article->id }})"
                    wire:key="tile-{{ $article->id }}"
                    title="{{ $article->designation }}"
                >
                    <span class="pos-tile-add"><x-filament::icon icon="heroicon-m-plus" /></span>
                    <span class="pos-tile-name">{{ $article->designation }}</span>
                    <span class="pos-tile-ref">{{ $article->reference }}</span>
                    <span class="pos-tile-foot">
                        <span class="pos-tile-price">{{ \App\Support\Money::format($article->prix_vente) }}</span>
                        @if ($stock <= 0)
                            <span class="pos-stock out">{{ __('app.pos.rupture') }}</span>
                        @else
                            <span class="pos-stock {{ $stock <= 5 ? 'low' : '' }}">{{ __('app.pos.stock') }} {{ rtrim(rtrim(number_format($stock, 2, '.', ''), '0'), '.') }}</span>
                        @endif
                    </span>
                </button>
            @endforeach
        </div>
    @endif
</div>
