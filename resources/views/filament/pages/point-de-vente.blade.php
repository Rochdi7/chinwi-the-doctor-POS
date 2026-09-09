<x-filament-panels::page>
    {{-- Styles live in public/css/pos.css (registered in AppServiceProvider)
         so they are fetched once, not re-sent inside every Livewire update. --}}

    @php
        $t = $this->totaux();
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
        {{-- ============ Product grid (own component: not re-rendered by cart actions) ============ --}}
        <livewire:pos-grille />

        {{-- ============ Cart ============ --}}
        <div class="pos-card pos-cart" wire:loading.class="pos-busy">
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
