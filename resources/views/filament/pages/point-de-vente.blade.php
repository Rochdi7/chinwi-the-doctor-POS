<x-filament-panels::page>
    {{-- Styles live in public/css/pos.css (registered in AppServiceProvider)
         so they are fetched once, not re-sent inside every Livewire update.
         That stylesheet also hides Filament's page header on this page: the
         compact .pos-bar below replaces it and reuses its actions. --}}

    @php
        $t = $this->totaux();
        $nbArticles = array_sum(array_map(fn ($l) => (float) ($l['quantite'] ?? 0), $panier));
        $nbArticles = rtrim(rtrim(number_format($nbArticles, 2, '.', ''), '0'), '.');
        $vide = $panier === [];
        $usb = $this->scannerUsb();
        $monnaie = $vide ? 0 : $this->monnaie();
        $reste = $vide ? 0 : $this->reste();
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

        {{-- ============ Workstation bar: identity, scan box, status, tools ============ --}}
        <header class="pos-bar">
            <div class="pos-bar-id">
                <span class="pos-bar-logo"><x-filament::icon icon="heroicon-o-shopping-cart" /></span>
                <div class="pos-bar-titles">
                    <strong>{{ __('app.pos.label') }}</strong>
                    <span>{{ \App\Models\Setting::get('societe_nom') }}</span>
                </div>
            </div>

            {{-- Scans are caught page-wide (see the root x-data); this box
                 is where they land by default and where a code is typed
                 by hand, followed by Enter. --}}
            <div class="pos-field pos-scanbox">
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

            <div class="pos-bar-status">
                {{-- What Windows says about the USB scanner (App\Support\ScannerUsb).
                     Nothing is shown while unknown: a host that cannot be asked
                     must not look like a till with a broken scanner. --}}
                @if ($usb['state'] !== 'unknown')
                    <div class="pos-usb {{ $usb['state'] }}" title="{{ __('app.pos.usb.aide_'.$usb['state']) }}">
                        <span class="dot"></span>
                        <span>{{ __('app.pos.usb.'.$usb['state']) }}</span>
                        @if ($usb['name'])
                            <span class="name">{{ $usb['name'] }}</span>
                        @endif
                    </div>
                @endif

                <span class="pos-meta" title="{{ auth()->user()?->email }}">
                    <x-filament::icon icon="heroicon-m-user-circle" />
                    {{ auth()->user()?->name }}
                </span>

                {{-- Wall clock, browser side only; Latin digits like every amount. --}}
                <span
                    class="pos-meta pos-num"
                    wire:ignore
                    x-data="{ now: '' }"
                    x-init="
                        const tick = () => now = new Date().toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
                        tick();
                        setInterval(tick, 15000);
                    "
                >
                    <x-filament::icon icon="heroicon-m-clock" />
                    <span x-text="now"></span>
                </span>
            </div>

            <div class="pos-bar-tools">
                {{-- The page's own header actions (PointDeVente::getHeaderActions),
                     moved here because the Filament header is hidden. --}}
                <button type="button" class="pos-tool" wire:click="mountAction('appairer')" title="{{ __('app.pos.appairer_titre') }}">
                    <x-filament::icon icon="heroicon-o-device-phone-mobile" />
                    <span>{{ __('app.pos.appairer') }}</span>
                </button>
                <a class="pos-tool" title="{{ __('app.pos.ventes') }}" href="{{ \App\Filament\Resources\InvoiceResource::getUrl('index') }}">
                    <x-filament::icon icon="heroicon-o-document-text" />
                    <span>{{ __('app.pos.ventes') }}</span>
                </a>
            </div>
        </header>

        {{-- ============ Product zone (own component: not re-rendered by cart actions) ============ --}}
        <livewire:pos-grille />

        {{-- ============ Checkout ============ --}}
        <aside class="pos-panel pos-cart" wire:loading.class="pos-busy">
            <div class="pos-cart-head">
                <h3>
                    <x-filament::icon icon="heroicon-o-shopping-bag" />
                    {{ __('app.pos.panier') }}
                    <span class="pos-badge {{ $vide ? '' : 'on' }}">{{ $nbArticles }}</span>
                </h3>
                <button type="button" class="pos-link" wire:click="vider" wire:confirm="{{ __('app.pos.vider') }} ?" @disabled($vide)>
                    <x-filament::icon icon="heroicon-o-trash" />
                    {{ __('app.pos.vider') }}
                </button>
            </div>

            <div class="pos-lines">
                @forelse ($panier as $key => $line)
                    <div class="pos-line" wire:key="line-{{ $key }}">
                        <div class="pos-line-info">
                            <div class="pos-line-name">{{ $line['designation'] }}</div>
                            <div class="pos-line-unit"><span class="pos-num">{{ \App\Support\Money::format($line['prix_unitaire']) }} &times; {{ rtrim(rtrim(number_format((float) $line['quantite'], 2, '.', ''), '0'), '.') }}</span></div>
                        </div>

                        <button type="button" class="pos-remove" wire:click="retirer('{{ $key }}')" title="{{ __('app.pos.retirer') }}">
                            <x-filament::icon icon="heroicon-o-x-mark" />
                        </button>

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

                        <div class="pos-line-total"><span class="pos-num">{{ \App\Support\Money::format($this->ligneTotal($line)) }}</span></div>
                    </div>
                @empty
                    <div class="pos-state">
                        <span class="pos-state-icon"><x-filament::icon icon="heroicon-o-qr-code" /></span>
                        <strong>{{ __('app.pos.panier_vide_titre') }}</strong>
                        <span>{{ __('app.pos.panier_vide') }}</span>
                    </div>
                @endforelse
            </div>

            <div class="pos-summary">
                <div class="pos-sum-row"><span>{{ __('app.item.total_ht') }}</span><span class="pos-num">{{ \App\Support\Money::format($t['ht']) }}</span></div>
                <div class="pos-sum-row"><span>{{ __('app.invoice.total_tva') }}</span><span class="pos-num">{{ \App\Support\Money::format($t['tva']) }}</span></div>
                <div class="pos-total">
                    <span class="pos-total-label">{{ __('app.pos.total') }}</span>
                    <span class="pos-total-value pos-num">{{ \App\Support\Money::format($t['ttc']) }}</span>
                </div>
            </div>

            <div class="pos-checkout">
                <div class="pos-who-how">
                <div class="pos-field">
                    <x-filament::icon icon="heroicon-o-user" />
                    <select class="pos-input" wire:model="client_id" aria-label="{{ __('app.invoice.client') }}">
                        <option value="">{{ __('app.vente.client_passage') }}</option>
                        @foreach ($this->clients as $c)
                            <option value="{{ $c->id }}">{{ $c->raison_sociale }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="pos-modes" role="radiogroup" aria-label="{{ __('app.pos.paiement') }}">
                    <button type="button" role="radio" aria-checked="{{ $mode === 'especes' ? 'true' : 'false' }}" class="pos-mode {{ $mode === 'especes' ? 'on' : '' }}" wire:click="$set('mode', 'especes')">
                        <x-filament::icon icon="heroicon-o-banknotes" />
                        {{ __('app.mode.especes') }}
                    </button>
                    <button type="button" role="radio" aria-checked="{{ $mode === 'tpe' ? 'true' : 'false' }}" class="pos-mode {{ $mode === 'tpe' ? 'on' : '' }}" wire:click="$set('mode', 'tpe')">
                        <x-filament::icon icon="heroicon-o-credit-card" />
                        {{ __('app.mode.tpe') }}
                    </button>
                </div>
                </div>

                <label class="pos-received">
                    <span class="pos-received-label">{{ __('app.pos.montant_recu') }}</span>
                    <span class="pos-received-box">
                        <input
                            type="text"
                            inputmode="decimal"
                            class="pos-received-input"
                            wire:model.live.debounce.300ms="montant_recu"
                            placeholder="{{ number_format($t['ttc'], 2, ',', ' ') }}"
                            autocomplete="off"
                        />
                        <span class="pos-received-cur">{{ \App\Support\Money::devise() }}</span>
                    </span>
                    <span class="pos-hint">{{ __('app.pos.montant_recu_aide') }}</span>
                </label>

                @if ($monnaie > 0)
                    <div class="pos-result is-change">
                        <span>{{ __('app.pos.monnaie') }}</span>
                        <strong class="pos-num">{{ \App\Support\Money::format($monnaie) }}</strong>
                    </div>
                @elseif ($reste > 0)
                    <div class="pos-result is-due">
                        <span>{{ __('app.pos.reste') }}</span>
                        <strong class="pos-num">{{ \App\Support\Money::format($reste) }}</strong>
                    </div>
                @endif

                <div class="pos-actions">
                    <button type="button" class="pos-btn primary" wire:click="encaisser" wire:loading.attr="disabled" @disabled($vide)>
                        <span class="pos-btn-main" wire:loading.remove wire:target="encaisser">
                            <x-filament::icon icon="heroicon-o-check-circle" />
                            <span>{{ __('app.pos.encaisser') }}</span>
                        </span>
                        <span class="pos-btn-main" wire:loading.flex wire:target="encaisser">
                            <span class="pos-spinner"></span>
                            <span>{{ __('app.pos.traitement') }}</span>
                        </span>
                        @unless ($vide)
                            <span class="amt pos-num">{{ \App\Support\Money::format($t['ttc']) }}</span>
                        @endunless
                    </button>
                    <button type="button" class="pos-btn secondary" wire:click="enregistrer" wire:loading.attr="disabled" @disabled($vide)>
                        <x-filament::icon icon="heroicon-o-clock" />
                        {{ __('app.pos.sans_paiement') }}
                    </button>
                </div>
            </div>
        </aside>
    </div>
</x-filament-panels::page>
