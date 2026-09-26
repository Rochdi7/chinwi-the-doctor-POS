/**
 * Display formatting only, mirroring App\Support\Money::format():
 * Latin digits, "1 234,50 DH", in every language.
 */
export function formatMoney(amount: number, devise: string): string {
    const [whole = '0', cents = '00'] = Math.abs(amount).toFixed(2).split('.');
    const grouped = whole.replace(/\B(?=(\d{3})+(?!\d))/g, ' ');

    return `${amount < 0 ? '-' : ''}${grouped},${cents} ${devise}`;
}

/** 2 -> "2", 1.5 -> "1.5", 0.25 -> "0.25" (as the Livewire till showed it). */
export function formatQty(quantity: number): string {
    return String(Number(quantity.toFixed(2)));
}

/** "12,5" or "12.5" typed by a cashier -> 12.5; blank or invalid -> null. */
export function parseAmount(raw: string): number | null {
    const cleaned = raw.replace(/\s/g, '').replace(',', '.');
    if (cleaned === '') return null;
    const value = Number(cleaned);

    return Number.isFinite(value) && value >= 0 ? value : null;
}
