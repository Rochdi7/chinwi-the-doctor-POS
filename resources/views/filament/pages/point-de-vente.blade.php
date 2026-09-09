<x-filament-panels::page>
    {{--
        Filament ships a fixed Tailwind build, so the layout below carries its
        own small stylesheet built on Filament colour tokens rather than
        relying on utility classes that may not exist in the bundle.
    --}}
    <style>
        .pos { display: grid; grid-template-columns: minmax(0, 1fr) 27rem; gap: 1.25rem; align-items: start; }
        @media (max-width: 1100px) { .pos { grid-template-columns: minmax(0, 1fr); } }

        .pos-card {
            background: white; border-radius: 1rem; overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,.06), 0 0 0 1px rgba(0,0,0,.05);
        }
        .dark .pos-card { background: rgb(var(--gray-900)); box-shadow: 0 0 0 1px rgba(255,255,255,.08); }

        /* ---- Inputs --------------------------------------------------- */
        .pos-field { position: relative; display: flex; align-items: center; }
        .pos-field > svg { position: absolute; inset-inline-start: .85rem; width: 1.2rem; height: 1.2rem; color: rgb(var(--gray-400)); pointer-events: none; }
        .pos-field > .pos-input { padding-inline-start: 2.6rem; }

        .pos-input {
            width: 100%; border-radius: .65rem; padding: .7rem .9rem; font-size: .95rem; line-height: 1.4;
            background-color: white; background-image: none; color: rgb(var(--gray-950));
            border: 1px solid rgb(var(--gray-300)); outline: none; box-shadow: 0 1px 2px rgba(0,0,0,.03);
            transition: border-color .1s ease, box-shadow .1s ease;
        }
        .pos-input::placeholder { color: rgb(var(--gray-400)); }
        .pos-input:focus { border-color: rgb(var(--primary-500)); box-shadow: 0 0 0 3px rgba(var(--primary-500), .18); }
        .dark .pos-input { background-color: rgb(var(--gray-800)); color: white; border-color: rgb(var(--gray-700)); }

        /* Filament styles every <select> with its own arrow; take over fully
           so the arrow is drawn once, on the correct side for RTL. */
        select.pos-input {
            appearance: none; -webkit-appearance: none; cursor: pointer;
            padding-inline-end: 2.5rem;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
            background-repeat: no-repeat; background-size: 1.25rem 1.25rem; background-position: right .7rem center;
        }
        [dir="rtl"] select.pos-input { background-position: left .7rem center; }

        /* ---- Product grid --------------------------------------------- */
        .pos-toolbar { display: flex; gap: .75rem; padding: 1rem 1.25rem; align-items: center; flex-wrap: wrap; border-bottom: 1px solid rgb(var(--gray-100)); }
        .dark .pos-toolbar { border-color: rgb(var(--gray-800)); }
        .pos-toolbar .pos-search { flex: 1 1 18rem; }
        .pos-toolbar .pos-cat { flex: 0 1 14rem; }
        .pos-count { font-size: .8rem; color: rgb(var(--gray-500)); white-space: nowrap; }

        .pos-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(11.5rem, 1fr)); gap: .85rem; padding: 1.25rem; }
        .pos-tile {
            position: relative; text-align: start; border-radius: .9rem; padding: 1rem; cursor: pointer;
            border: 1px solid rgb(var(--gray-200)); background: white;
            display: flex; flex-direction: column; gap: .3rem; min-height: 8.5rem; width: 100%;
            transition: transform .08s ease, border-color .08s ease, box-shadow .08s ease;
        }
        .pos-tile:hover { border-color: rgb(var(--primary-400)); box-shadow: 0 6px 16px -6px rgba(0,0,0,.18); transform: translateY(-2px); }
        .pos-tile:active { transform: scale(.98); }
        .pos-tile:focus-visible { outline: none; box-shadow: 0 0 0 3px rgba(var(--primary-500), .3); }
        .dark .pos-tile { background: rgb(var(--gray-800)); border-color: rgb(var(--gray-700)); }
        .pos-tile-add {
            position: absolute; top: .6rem; inset-inline-end: .6rem; width: 1.75rem; height: 1.75rem; border-radius: 999px;
            display: grid; place-items: center; background: rgb(var(--primary-600)); color: white;
            opacity: 0; transform: scale(.7); transition: opacity .1s ease, transform .1s ease;
        }
        .pos-tile-add svg { width: 1rem; height: 1rem; }
        .pos-tile:hover .pos-tile-add { opacity: 1; transform: scale(1); }
        .pos-tile-name {
            font-weight: 600; line-height: 1.3; color: rgb(var(--gray-950)); padding-inline-end: 1.5rem;
            display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
        }
        .dark .pos-tile-name { color: white; }
        .pos-tile-ref { font-size: .72rem; color: rgb(var(--gray-400)); font-family: ui-monospace, monospace; }
        .pos-tile-foot { margin-top: auto; display: flex; align-items: flex-end; justify-content: space-between; gap: .5rem; padding-top: .5rem; }
        .pos-tile-price { font-weight: 800; font-size: 1.1rem; color: rgb(var(--primary-600)); white-space: nowrap; }
        .dark .pos-tile-price { color: rgb(var(--primary-400)); }
        .pos-stock { font-size: .68rem; font-weight: 600; padding: .15rem .5rem; border-radius: 999px; background: rgb(var(--gray-100)); color: rgb(var(--gray-600)); white-space: nowrap; }
        .pos-stock.low { background: rgb(var(--warning-100)); color: rgb(var(--warning-700)); }
        .pos-stock.out { background: rgb(var(--danger-100)); color: rgb(var(--danger-700)); }
        .dark .pos-stock { background: rgb(var(--gray-700)); color: rgb(var(--gray-200)); }
        .dark .pos-stock.low { background: rgba(var(--warning-500), .25); color: rgb(var(--warning-300)); }
        .dark .pos-stock.out { background: rgba(var(--danger-500), .25); color: rgb(var(--danger-300)); }

        .pos-empty { padding: 3rem 1rem; text-align: center; color: rgb(var(--gray-500)); display: flex; flex-direction: column; align-items: center; gap: .75rem; }
        .pos-empty svg { width: 2.75rem; height: 2.75rem; color: rgb(var(--gray-300)); }
        .dark .pos-empty svg { color: rgb(var(--gray-600)); }

        /* ---- Cart ----------------------------------------------------- */
        .pos-cart { position: sticky; top: 1rem; display: flex; flex-direction: column; max-height: calc(100vh - 2rem); }
        .pos-cart-head { padding: 1rem 1.25rem; display: flex; flex-direction: column; gap: .75rem; border-bottom: 1px solid rgb(var(--gray-100)); }
        .dark .pos-cart-head { border-color: rgb(var(--gray-800)); }
        .pos-cart-title { display: flex; justify-content: space-between; align-items: center; }
        .pos-cart-title h3 { font-size: 1.05rem; font-weight: 700; display: flex; align-items: center; gap: .5rem; margin: 0; }
        .pos-cart-title h3 svg { width: 1.25rem; height: 1.25rem; color: rgb(var(--primary-600)); }
        .pos-badge { font-size: .75rem; font-weight: 600; padding: .15rem .6rem; border-radius: 999px; background: rgb(var(--gray-100)); color: rgb(var(--gray-600)); }
        .pos-badge.on { background: rgb(var(--primary-50)); color: rgb(var(--primary-700)); }
        .dark .pos-badge { background: rgb(var(--gray-800)); color: rgb(var(--gray-300)); }
        .dark .pos-badge.on { background: rgba(var(--primary-500), .15); color: rgb(var(--primary-300)); }
        .pos-link { font-size: .8rem; font-weight: 600; color: rgb(var(--danger-600)); display: inline-flex; align-items: center; gap: .25rem; }
        .pos-link svg { width: 1rem; height: 1rem; }
        .pos-link:hover { text-decoration: underline; }
        .pos-link:disabled { opacity: .35; cursor: not-allowed; text-decoration: none; }

        .pos-scan { font-size: 1.15rem; font-weight: 600; letter-spacing: .03em; padding-block: .85rem; border: 2px solid rgb(var(--primary-500)); background-color: rgb(var(--primary-50)); }
        .pos-scan:focus { background-color: white; }
        .dark .pos-scan { background-color: rgba(var(--primary-500), .1); }
        .dark .pos-scan:focus { background-color: rgb(var(--gray-800)); }
        .pos-field.scan > svg { color: rgb(var(--primary-600)); }
        .pos-kbd { position: absolute; inset-inline-end: .75rem; font-size: .65rem; font-weight: 600; color: rgb(var(--gray-400)); border: 1px solid rgb(var(--gray-300)); border-radius: .3rem; padding: .1rem .35rem; pointer-events: none; }
        .dark .pos-kbd { border-color: rgb(var(--gray-600)); }

        .pos-lines { flex: 1 1 auto; min-height: 7rem; overflow-y: auto; }
        .pos-line { display: grid; grid-template-columns: minmax(0,1fr) auto; gap: .35rem .75rem; align-items: center; padding: .75rem 1.25rem; border-bottom: 1px solid rgb(var(--gray-100)); }
        .dark .pos-line { border-color: rgb(var(--gray-800)); }
        .pos-line:hover { background: rgb(var(--gray-50)); }
        .dark .pos-line:hover { background: rgb(var(--gray-800)); }
        .pos-line-name { font-weight: 600; color: rgb(var(--gray-950)); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .dark .pos-line-name { color: white; }
        .pos-line-unit { font-size: .75rem; color: rgb(var(--gray-500)); }
        .pos-line-right { display: flex; align-items: center; gap: .6rem; }
        .pos-line-total { font-weight: 700; white-space: nowrap; text-align: end; min-width: 6.5rem; color: rgb(var(--gray-950)); }
        .dark .pos-line-total { color: white; }
        .pos-qty { display: inline-flex; align-items: center; border: 1px solid rgb(var(--gray-300)); border-radius: .55rem; overflow: hidden; background: white; }
        .dark .pos-qty { border-color: rgb(var(--gray-700)); background: rgb(var(--gray-800)); }
        .pos-qty button { width: 2.2rem; height: 2.2rem; font-size: 1.2rem; font-weight: 700; color: rgb(var(--gray-700)); background: rgb(var(--gray-50)); }
        .pos-qty button:hover { background: rgb(var(--primary-50)); color: rgb(var(--primary-700)); }
        .dark .pos-qty button { color: rgb(var(--gray-200)); background: rgb(var(--gray-700)); }
        .pos-qty input { width: 3.2rem; height: 2.2rem; text-align: center; border: 0; background: transparent; font-weight: 700; color: inherit; outline: none; -moz-appearance: textfield; font-size: .95rem; }
        .pos-qty input:focus { background: rgb(var(--primary-50)); }
        .dark .pos-qty input:focus { background: rgba(var(--primary-500), .15); }
        .pos-qty input::-webkit-outer-spin-button, .pos-qty input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
        .pos-remove { width: 1.9rem; height: 1.9rem; border-radius: .5rem; display: grid; place-items: center; color: rgb(var(--gray-400)); }
        .pos-remove svg { width: 1.05rem; height: 1.05rem; }
        .pos-remove:hover { color: rgb(var(--danger-600)); background: rgb(var(--danger-50)); }
        .dark .pos-remove:hover { background: rgba(var(--danger-500), .15); }

        .pos-totals { padding: .9rem 1.25rem; display: flex; flex-direction: column; gap: .3rem; border-top: 1px solid rgb(var(--gray-100)); background: rgb(var(--gray-50)); }
        .dark .pos-totals { border-color: rgb(var(--gray-800)); background: rgb(var(--gray-950)); }
        .pos-totals .row { display: flex; justify-content: space-between; font-size: .85rem; color: rgb(var(--gray-500)); }
        .pos-totals .row.grand { font-size: 1.55rem; font-weight: 800; color: rgb(var(--gray-950)); margin-top: .15rem; align-items: baseline; }
        .dark .pos-totals .row.grand { color: white; }
        .pos-totals .row.change { font-size: 1.05rem; font-weight: 700; color: rgb(var(--success-600)); }
        .pos-totals .row.due { font-size: 1.05rem; font-weight: 700; color: rgb(var(--danger-600)); }

        .pos-pay { padding: 1rem 1.25rem 1.25rem; display: flex; flex-direction: column; gap: .75rem; border-top: 1px solid rgb(var(--gray-100)); }
        .dark .pos-pay { border-color: rgb(var(--gray-800)); }
        .pos-modes { display: grid; grid-template-columns: 1fr 1fr; gap: .5rem; }
        .pos-mode {
            padding: .65rem; border-radius: .65rem; border: 1px solid rgb(var(--gray-300)); font-weight: 600; font-size: .9rem;
            display: flex; align-items: center; justify-content: center; gap: .45rem; color: rgb(var(--gray-600)); background: white;
        }
        .pos-mode svg { width: 1.1rem; height: 1.1rem; }
        .pos-mode:hover { border-color: rgb(var(--gray-400)); }
        .pos-mode.on { border-color: rgb(var(--primary-600)); background: rgb(var(--primary-50)); color: rgb(var(--primary-700)); box-shadow: inset 0 0 0 1px rgb(var(--primary-600)); }
        .dark .pos-mode { border-color: rgb(var(--gray-700)); color: rgb(var(--gray-300)); background: rgb(var(--gray-800)); }
        .dark .pos-mode.on { background: rgba(var(--primary-500), .15); color: rgb(var(--primary-300)); }

        .pos-actions { display: grid; grid-template-columns: 1fr 1fr; gap: .5rem; }
        .pos-btn { border-radius: .7rem; padding: .8rem 1rem; font-weight: 700; font-size: .95rem; display: flex; align-items: center; justify-content: center; gap: .5rem; transition: background .1s ease, transform .05s ease; }
        .pos-btn svg { width: 1.2rem; height: 1.2rem; }
        .pos-btn:active { transform: scale(.98); }
        .pos-btn.primary { grid-column: 1 / -1; padding: 1rem; font-size: 1.15rem; background: rgb(var(--primary-600)); color: white; box-shadow: 0 4px 12px -4px rgba(var(--primary-600), .6); }
        .pos-btn.primary:hover { background: rgb(var(--primary-700)); }
        .pos-btn.primary .amt { font-weight: 800; opacity: .95; }
        .pos-btn.secondary { border: 1px solid rgb(var(--gray-300)); color: rgb(var(--gray-700)); background: white; }
        .pos-btn.secondary:hover { background: rgb(var(--gray-50)); }
        .dark .pos-btn.secondary { border-color: rgb(var(--gray-700)); color: rgb(var(--gray-200)); background: rgb(var(--gray-800)); }
        .pos-btn:disabled { opacity: .45; cursor: not-allowed; transform: none; box-shadow: none; }
        .pos-hint { font-size: .72rem; color: rgb(var(--gray-400)); margin-top: .3rem; }
    </style>

    @php
        $t = $this->totaux();
        $nbLignes = count($panier);
        $nbArticles = array_sum(array_map(fn ($l) => (float) ($l['quantite'] ?? 0), $panier));
        $nbArticles = rtrim(rtrim(number_format($nbArticles, 2, '.', ''), '0'), '.');
        $vide = $panier === [];
    @endphp

    <div
        class="pos"
        x-data="{
            focusScan() { this.$refs.scan?.focus(); },
        }"
        x-init="focusScan()"
        {{-- A scanner types wherever the cursor is. If the cashier last clicked
             a tile or the page background, route those keystrokes to the scan
             box instead of losing them. --}}
        x-on:keydown.window="
            const t = $event.target;
            const typing = ['INPUT','TEXTAREA','SELECT'].includes(t.tagName) || t.isContentEditable;
            if (!typing && !$event.ctrlKey && !$event.metaKey && !$event.altKey && $event.key.length === 1) {
                focusScan();
            }
        "
    >
        {{-- ============ Product grid ============ --}}
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
                        <button
                            type="button"
                            class="pos-tile"
                            wire:click="ajouter({{ $article->id }})"
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

        {{-- ============ Cart ============ --}}
        <div class="pos-card pos-cart">
            <div class="pos-cart-head">
                <div class="pos-field scan">
                    <x-filament::icon icon="heroicon-o-qr-code" />
                    <input
                        type="text"
                        x-ref="scan"
                        class="pos-input pos-scan"
                        wire:model="scan"
                        wire:keydown.enter.prevent="scanner"
                        placeholder="{{ __('app.scan.placeholder') }}"
                        autocomplete="off"
                        autofocus
                        enterkeyhint="done"
                    />
                    <span class="pos-kbd">&#9166;</span>
                </div>

                <div class="pos-field">
                    <x-filament::icon icon="heroicon-o-user" />
                    <select class="pos-input" wire:model="client_id">
                        <option value="">{{ __('app.vente.client_passage') }}</option>
                        @foreach ($this->clients as $c)
                            <option value="{{ $c->id }}">{{ $c->raison_sociale }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="pos-cart-title">
                    <h3>
                        <x-filament::icon icon="heroicon-o-shopping-cart" />
                        {{ __('app.pos.panier') }}
                        <span class="pos-badge {{ $vide ? '' : 'on' }}">{{ $nbArticles }}</span>
                    </h3>
                    <button type="button" class="pos-link" wire:click="vider" wire:confirm="{{ __('app.pos.vider') }} ?" @disabled($vide)>
                        <x-filament::icon icon="heroicon-o-trash" />
                        {{ __('app.pos.vider') }}
                    </button>
                </div>
            </div>

            <div class="pos-lines">
                @forelse ($panier as $key => $line)
                    <div class="pos-line" wire:key="line-{{ $key }}">
                        <div style="min-width: 0">
                            <div class="pos-line-name">{{ $line['designation'] }}</div>
                            <div class="pos-line-unit">{{ \App\Support\Money::format($line['prix_unitaire']) }} &times; {{ rtrim(rtrim(number_format((float) $line['quantite'], 2, '.', ''), '0'), '.') }}</div>
                        </div>

                        <div class="pos-line-right">
                            <div class="pos-qty">
                                <button type="button" wire:click="moins('{{ $key }}')" title="{{ __('app.pos.moins') }}">&minus;</button>
                                <input
                                    type="number"
                                    step="any"
                                    min="0"
                                    value="{{ $line['quantite'] }}"
                                    wire:change="quantite('{{ $key }}', $event.target.value)"
                                    x-on:keydown.enter.prevent="$event.target.blur()"
                                />
                                <button type="button" wire:click="plus('{{ $key }}')" title="{{ __('app.pos.plus') }}">+</button>
                            </div>
                            <div class="pos-line-total">{{ \App\Support\Money::format($this->ligneTotal($line)) }}</div>
                            <button type="button" class="pos-remove" wire:click="retirer('{{ $key }}')" title="{{ __('app.pos.retirer') }}">
                                <x-filament::icon icon="heroicon-o-x-mark" />
                            </button>
                        </div>
                    </div>
                @empty
                    <div class="pos-empty">
                        <x-filament::icon icon="heroicon-o-qr-code" />
                        <span>{{ __('app.pos.panier_vide') }}</span>
                    </div>
                @endforelse
            </div>

            <div class="pos-totals">
                <div class="row"><span>{{ __('app.item.total_ht') }}</span><span>{{ \App\Support\Money::format($t['ht']) }}</span></div>
                <div class="row"><span>{{ __('app.invoice.total_tva') }}</span><span>{{ \App\Support\Money::format($t['tva']) }}</span></div>
                <div class="row grand"><span>{{ __('app.pos.total') }}</span><span>{{ \App\Support\Money::format($t['ttc']) }}</span></div>
                @if (! $vide && $this->monnaie() > 0)
                    <div class="row change"><span>{{ __('app.pos.monnaie') }}</span><span>{{ \App\Support\Money::format($this->monnaie()) }}</span></div>
                @elseif (! $vide && $this->reste() > 0)
                    <div class="row due"><span>{{ __('app.pos.reste') }}</span><span>{{ \App\Support\Money::format($this->reste()) }}</span></div>
                @endif
            </div>

            <div class="pos-pay">
                <div class="pos-modes">
                    <button type="button" class="pos-mode {{ $mode === 'especes' ? 'on' : '' }}" wire:click="$set('mode', 'especes')">
                        <x-filament::icon icon="heroicon-o-banknotes" />
                        {{ __('app.mode.especes') }}
                    </button>
                    <button type="button" class="pos-mode {{ $mode === 'tpe' ? 'on' : '' }}" wire:click="$set('mode', 'tpe')">
                        <x-filament::icon icon="heroicon-o-credit-card" />
                        {{ __('app.mode.tpe') }}
                    </button>
                </div>

                <div>
                    <div class="pos-field">
                        <x-filament::icon icon="heroicon-o-calculator" />
                        <input
                            type="text"
                            inputmode="decimal"
                            class="pos-input"
                            wire:model.live.debounce.300ms="montant_recu"
                            placeholder="{{ __('app.pos.montant_recu') }} ({{ \App\Support\Money::devise() }})"
                            autocomplete="off"
                        />
                    </div>
                    <div class="pos-hint">{{ __('app.pos.montant_recu_aide') }}</div>
                </div>

                <div class="pos-actions">
                    <button type="button" class="pos-btn primary" wire:click="encaisser" wire:loading.attr="disabled" @disabled($vide)>
                        <x-filament::icon icon="heroicon-o-check-circle" />
                        <span>{{ __('app.pos.encaisser') }}</span>
                        @unless ($vide)
                            <span class="amt">&middot; {{ \App\Support\Money::format($t['ttc']) }}</span>
                        @endunless
                    </button>
                    <button type="button" class="pos-btn secondary" wire:click="enregistrer" wire:loading.attr="disabled" @disabled($vide) style="grid-column: 1 / -1">
                        <x-filament::icon icon="heroicon-o-clock" />
                        {{ __('app.pos.sans_paiement') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
