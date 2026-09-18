<x-filament-panels::page>
    {{-- Styles live in public/css/pos.css (registered in AppServiceProvider)
         so they are fetched once, not re-sent inside every Livewire update. --}}

    @php
        $t = $this->totaux();
        $nbArticles = array_sum(array_map(fn ($l) => (float) ($l['quantite'] ?? 0), $panier));
        $nbArticles = rtrim(rtrim(number_format($nbArticles, 2, '.', ''), '0'), '.');
        $vide = $panier === [];
    @endphp

    {{--
        A USB scanner is a keyboard: it types the code (~10 ms per character)
        wherever the cursor happens to be, and most units (Honeywell included)
        send no Enter unless programmed to. So the page, not one input, listens
        for scans: a burst of 8+ characters at scanner speed, then silence, is
        a scan. It is added to the cart whichever field received it, and the
        characters are removed from that field so a search box or a quantity
        does not keep the barcode. Typing by hand never comes in that fast,
        so the scan box still waits for Enter when the cashier keys a code.
    --}}
    <div
        class="pos"
        x-data="{
            buffer: '',
            last: 0,
            timer: null,
            // ?debug in the URL shows what the scanner actually sends: each
            // key with its delay, and every decision. For diagnosing a till
            // remotely without touching the scanner.
            debug: new URLSearchParams(location.search).has('debug'),
            log: [],
            trace(msg) {
                if (!this.debug) return;
                this.log.push(msg);
                if (this.log.length > 40) this.log.shift();
            },
            focusScan() { this.$refs.scan?.focus(); },
            isScanBox(el) { return el === this.$refs.scan; },
            typing(el) { return ['INPUT','TEXTAREA','SELECT'].includes(el.tagName) || el.isContentEditable; },
            key(e) {
                if (e.ctrlKey || e.metaKey || e.altKey) return;

                if (e.key === 'Enter') {
                    clearTimeout(this.timer);
                    this.trace('Enter in ' + (e.target.tagName || '?') + ' buffer=' + JSON.stringify(this.buffer));
                    if (this.isScanBox(e.target)) {
                        // Hand-typed code (or a scanner that does send Enter).
                        e.preventDefault();
                        this.buffer = '';
                        this.submit(e.target.value, e.target);
                    } else if (this.buffer.length >= 8) {
                        e.preventDefault();
                        this.submit(this.buffer, e.target);
                    }
                    return;
                }

                if (e.key.length !== 1) return;

                // Nowhere to type: send the keystroke to the scan box.
                if (!this.typing(e.target)) this.focusScan();

                const now = performance.now();
                const gap = Math.round(now - this.last);
                this.buffer = gap < 80 ? this.buffer + e.key : e.key;
                this.last = now;
                this.trace('key ' + JSON.stringify(e.key) + ' +' + gap + 'ms in ' + (e.target.tagName || '?') + ' buffer=' + this.buffer.length);

                clearTimeout(this.timer);
                this.timer = setTimeout(() => {
                    if (this.buffer.length >= 8) {
                        this.submit(this.buffer, document.activeElement);
                    } else {
                        this.trace('idle, buffer too short (' + this.buffer.length + ') -> not a scan');
                    }
                }, 150);
            },
            submit(code, el) {
                code = (code || '').trim();
                this.buffer = '';
                if (code === '') return;
                this.trace('SCAN -> ' + JSON.stringify(code) + ' (sending to server)');

                // Take the barcode back out of whatever field caught it.
                if (el && this.typing(el) && typeof el.value === 'string') {
                    el.value = this.isScanBox(el) || el.value.endsWith(code)
                        ? (this.isScanBox(el) ? '' : el.value.slice(0, -code.length))
                        : el.value;
                    el.dispatchEvent(new Event('input', { bubbles: true }));
                }

                $wire.scanner(code);
            },
        }"
        {{-- A phone on the Scanner page pushes codes to a short server-side
             queue; drain it a few times a second so a scan taken on the phone
             appears in this cart on its own. --}}
        x-init="
            focusScan();
            if (debug) trace('debug on — scan now');
            setInterval(async () => {
                if (document.hidden) return;
                const n = await $wire.recupererScans();
                if (n) trace('phone: ' + n + ' scan(s)');
            }, 900);
        "
        x-on:keydown.window="key($event)"
        x-on:pos-scan-result.window="trace('server: ' + ($event.detail?.message ?? JSON.stringify($event.detail)))"
    >
        <div class="pos-debug" x-show="debug" x-cloak>
            <strong>SCAN DEBUG</strong>
            <template x-for="(line, i) in log" :key="i"><div x-text="line"></div></template>
        </div>
        {{-- ============ Product grid (own component: not re-rendered by cart actions) ============ --}}
        <livewire:pos-grille />

        {{-- ============ Cart ============ --}}
        <div class="pos-card pos-cart" wire:loading.class="pos-busy">
            <div class="pos-cart-head">
                {{-- Scans are caught page-wide (see the root x-data); this box
                     is where they land by default and where a code is typed
                     by hand, followed by Enter. --}}
                <div class="pos-field scan">
                    <x-filament::icon icon="heroicon-o-qr-code" />
                    <input
                        type="text"
                        x-ref="scan"
                        class="pos-input pos-scan"
                        wire:model="scan"
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
