import { useEffect, useRef, type RefObject } from 'react';

/** Characters closer together than this come from a scanner, not a hand. */
const BURST_GAP_MS = 80;
/** Silence after a burst that ends a scan (scanners that send no Enter). */
const IDLE_MS = 150;
/** Shortest burst taken as a barcode (EAN-8 and up). */
const MIN_LENGTH = 8;

const isTypingTarget = (el: EventTarget | null): el is HTMLElement =>
    el instanceof HTMLElement && (['INPUT', 'TEXTAREA', 'SELECT'].includes(el.tagName) || el.isContentEditable);

/** Set a React-controlled input's value so its onChange sees it. */
function setNativeValue(el: HTMLInputElement | HTMLTextAreaElement, value: string) {
    const proto = el instanceof HTMLTextAreaElement ? HTMLTextAreaElement.prototype : HTMLInputElement.prototype;
    Object.getOwnPropertyDescriptor(proto, 'value')?.set?.call(el, value);
    el.dispatchEvent(new Event('input', { bubbles: true }));
}

/**
 * Page-wide USB scanner support, ported from the Livewire till's Alpine code
 * (resources/views/filament/pages/point-de-vente.blade.php), rule for rule.
 *
 * A USB scanner is a keyboard: it types the code (~10 ms per character)
 * wherever the cursor is, and many units send no Enter. So the page, not one
 * input, listens: a burst of 8+ characters at scanner speed, then silence,
 * is a scan. It is taken out of whatever field received it and handed to
 * `onScan`. Typing by hand is never that fast, so the scan box still waits
 * for Enter when a code is keyed in by hand.
 */
export function useKeyboardScanner(scanBox: RefObject<HTMLInputElement | null>, onScan: (code: string) => void, enabled = true) {
    const onScanRef = useRef(onScan);
    onScanRef.current = onScan;

    useEffect(() => {
        if (!enabled) return;

        let buffer = '';
        let last = 0;
        let timer: number | undefined;

        const isScanBox = (el: EventTarget | null) => el === scanBox.current;

        const submit = (raw: string, el: Element | null) => {
            const code = raw.trim();
            buffer = '';
            if (code === '') return;

            // Take the barcode back out of whatever field caught it.
            if ((el instanceof HTMLInputElement || el instanceof HTMLTextAreaElement) && typeof el.value === 'string') {
                if (isScanBox(el)) setNativeValue(el, '');
                else if (el.value.endsWith(code)) setNativeValue(el, el.value.slice(0, -code.length));
            }

            onScanRef.current(code);
        };

        const onKeyDown = (e: KeyboardEvent) => {
            if (e.ctrlKey || e.metaKey || e.altKey) return;

            if (e.key === 'Enter') {
                window.clearTimeout(timer);
                if (isScanBox(e.target)) {
                    // Hand-typed code (or a scanner that does send Enter).
                    e.preventDefault();
                    buffer = '';
                    submit((e.target as HTMLInputElement).value, e.target as Element);
                } else if (buffer.length >= MIN_LENGTH) {
                    e.preventDefault();
                    submit(buffer, e.target as Element);
                }
                return;
            }

            if (e.key.length !== 1) return;

            // Nowhere to type: send the keystroke to the scan box.
            if (!isTypingTarget(e.target)) scanBox.current?.focus();

            const now = performance.now();
            buffer = now - last < BURST_GAP_MS ? buffer + e.key : e.key;
            last = now;

            window.clearTimeout(timer);
            timer = window.setTimeout(() => {
                if (buffer.length >= MIN_LENGTH) submit(buffer, document.activeElement);
            }, IDLE_MS);
        };

        window.addEventListener('keydown', onKeyDown);
        return () => {
            window.removeEventListener('keydown', onKeyDown);
            window.clearTimeout(timer);
        };
    }, [scanBox, enabled]);
}
