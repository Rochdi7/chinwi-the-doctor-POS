{{--
    Pair another device with this till.

    The QR is drawn in the browser rather than on the server: shared hosting
    has no QR library installed, and adding a PHP dependency for one picture
    is not worth it.
--}}
<div
    class="pos-pair"
    x-data="{
        url: @js($url),
        copied: false,
        async draw() {
            if (!window.QRCode) {
                await new Promise((resolve, reject) => {
                    const tag = document.createElement('script');
                    tag.src = 'https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js';
                    tag.onload = resolve;
                    tag.onerror = reject;
                    document.head.appendChild(tag);
                });
            }

            this.$refs.qr.innerHTML = '';
            new QRCode(this.$refs.qr, { text: this.url, width: 220, height: 220 });
        },
        copy() {
            navigator.clipboard?.writeText(this.url);
            this.copied = true;
            setTimeout(() => (this.copied = false), 1500);
        },
    }"
    x-init="draw()"
>
    <div class="pos-pair-qr" x-ref="qr"></div>

    <div class="pos-pair-url" x-text="url"></div>

    <button type="button" class="pos-pair-copy" x-on:click="copy()">
        <span x-show="!copied">{{ __('app.pos.appairer_copier') }}</span>
        <span x-show="copied" x-cloak>{{ __('app.pos.appairer_copie') }}</span>
    </button>
</div>
