<x-filament-panels::page>
    {{--
        The phone as the scanner. The camera runs in the page; decoding uses
        the browser's own BarcodeDetector where it exists (Android/Chrome),
        and falls back to ZXing for iPhone, which has no BarcodeDetector.

        Each code found is sent to the server, which pushes it to the till
        open on the PC. The same code is ignored for a moment afterwards:
        the camera sees the same barcode 30 times a second, and the cashier
        means one product.
    --}}
    <script src="https://cdn.jsdelivr.net/npm/@zxing/library@0.21.3/umd/index.min.js" defer></script>

    <div
        class="scanner"
        x-data="{
            running: false,
            status: @js(__('app.scanner.pret')),
            ok: null,
            last: '',
            lastAt: 0,
            history: [],
            stream: null,
            reader: null,
            detector: null,
            loop: null,

            async start() {
                this.ok = null;

                if (!window.isSecureContext) {
                    this.say(@js(__('app.scanner.https')), false);
                    return;
                }

                try {
                    // The back camera, as close to the product as it can focus.
                    this.stream = await navigator.mediaDevices.getUserMedia({
                        video: { facingMode: { ideal: 'environment' }, width: { ideal: 1280 } },
                        audio: false,
                    });
                } catch (e) {
                    this.say(@js(__('app.scanner.refus')), false);
                    return;
                }

                const video = this.$refs.video;
                video.srcObject = this.stream;
                // iOS refuses to play an inline video that is not muted.
                video.setAttribute('playsinline', '');
                video.muted = true;
                await video.play();

                this.running = true;
                this.status = @js(__('app.scanner.pret'));

                // Chrome/Android decode natively. iPhone has no
                // BarcodeDetector, so ZXing reads the frames instead; both
                // go through the same tick loop, which keeps one code path
                // for the throttling and the canvas below.
                if ('BarcodeDetector' in window) {
                    this.detector = new BarcodeDetector({
                        formats: ['ean_13', 'ean_8', 'upc_a', 'upc_e', 'code_128', 'code_39', 'itf', 'qr_code'],
                    });
                } else if (window.ZXing) {
                    this.reader = new ZXing.MultiFormatReader();
                    const hints = new Map();
                    hints.set(ZXing.DecodeHintType.TRY_HARDER, true);
                    hints.set(ZXing.DecodeHintType.POSSIBLE_FORMATS, [
                        ZXing.BarcodeFormat.EAN_13, ZXing.BarcodeFormat.EAN_8,
                        ZXing.BarcodeFormat.UPC_A, ZXing.BarcodeFormat.UPC_E,
                        ZXing.BarcodeFormat.CODE_128, ZXing.BarcodeFormat.CODE_39,
                        ZXing.BarcodeFormat.ITF, ZXing.BarcodeFormat.QR_CODE,
                    ]);
                    this.reader.setHints(hints);
                } else {
                    this.say(@js(__('app.scanner.indispo')), false);
                    this.stop();
                    return;
                }

                this.tick();
            },

            async tick() {
                if (!this.running) return;

                try {
                    const code = this.detector
                        ? await this.readNative()
                        : this.readZxing();

                    if (code) this.found(code);
                } catch (e) {
                    // A dropped or unreadable frame is normal; keep looking.
                }

                this.loop = setTimeout(() => this.tick(), 120);
            },

            async readNative() {
                const codes = await this.detector.detect(this.$refs.video);

                return codes.length ? codes[0].rawValue : null;
            },

            /** One frame through ZXing, the iPhone path. */
            readZxing() {
                const video = this.$refs.video;

                if (!video.videoWidth) return null;

                const canvas = this.$refs.canvas;
                canvas.width = video.videoWidth;
                canvas.height = video.videoHeight;
                canvas.getContext('2d', { willReadFrequently: true })
                    .drawImage(video, 0, 0, canvas.width, canvas.height);

                const source = new ZXing.HTMLCanvasElementLuminanceSource(canvas);
                const bitmap = new ZXing.BinaryBitmap(new ZXing.HybridBinarizer(source));

                try {
                    return this.reader.decode(bitmap).getText();
                } finally {
                    // The reader keeps state between frames; without this a
                    // second product is never seen.
                    this.reader.reset();
                }
            },

            async found(code) {
                code = (code || '').trim();
                const now = Date.now();

                // Same barcode still under the camera: one product, one scan.
                if (code === '' || (code === this.last && now - this.lastAt < 1500)) return;

                this.last = code;
                this.lastAt = now;
                this.beep();

                const res = await $wire.envoyer(code);
                this.say(res.message, res.ok);

                if (res.ok) {
                    this.history.unshift(res.message);
                    this.history = this.history.slice(0, 8);
                }
            },

            say(message, ok) {
                this.status = message;
                this.ok = ok;
                if (navigator.vibrate) navigator.vibrate(ok ? 40 : [60, 40, 60]);
            },

            // A short tone: the cashier keeps their eyes on the products.
            beep() {
                try {
                    const ctx = new (window.AudioContext || window.webkitAudioContext)();
                    const osc = ctx.createOscillator();
                    const gain = ctx.createGain();
                    osc.frequency.value = 1750;
                    gain.gain.value = 0.15;
                    osc.connect(gain).connect(ctx.destination);
                    osc.start();
                    osc.stop(ctx.currentTime + 0.07);
                    setTimeout(() => ctx.close(), 300);
                } catch (e) {}
            },

            stop() {
                this.running = false;
                clearTimeout(this.loop);
                this.reader = null;
                this.detector = null;
                this.stream?.getTracks().forEach((t) => t.stop());
                this.stream = null;
            },
        }"
        x-on:beforeunload.window="stop()"
        x-init="
            $el.addEventListener('livewire:navigating', () => stop());
            // On a phone Filament opens with the sidebar over the page; this
            // one is held one-handed at the counter, so close it.
            $store.sidebar?.close?.();
        "
    >
        <div class="scanner-card">
            <h2>{{ __('app.scanner.titre') }}</h2>
            <p class="scanner-aide">{{ __('app.scanner.aide') }}</p>

            <div class="scanner-view" :class="running ? 'on' : ''">
                <video x-ref="video" playsinline muted></video>
                {{-- Frames land here for ZXing to read (iPhone path). --}}
                <canvas x-ref="canvas" hidden></canvas>
                <div class="scanner-sight" x-show="running"></div>
            </div>

            <div class="scanner-status" :class="ok === true ? 'ok' : (ok === false ? 'ko' : '')" x-text="status"></div>

            <button type="button" class="scanner-btn" x-show="!running" x-on:click="start()">
                <x-filament::icon icon="heroicon-o-camera" />
                {{ __('app.scanner.demarrer') }}
            </button>
            <button type="button" class="scanner-btn stop" x-show="running" x-cloak x-on:click="stop()">
                <x-filament::icon icon="heroicon-o-stop" />
                {{ __('app.scanner.arreter') }}
            </button>

            <template x-if="history.length">
                <div class="scanner-history">
                    <strong>{{ __('app.scanner.derniers') }}</strong>
                    <template x-for="(item, i) in history" :key="i">
                        <div x-text="item"></div>
                    </template>
                </div>
            </template>
        </div>
    </div>
</x-filament-panels::page>
