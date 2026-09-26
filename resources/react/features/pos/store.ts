import { create } from 'zustand';
import { uuid } from '@/lib/uuid';
import type { Article, CartItemInput, PaymentMode } from '@/types/api';

/**
 * The cart being built at the counter: local UI state only.
 *
 * It holds what the cashier picked (article, quantity) plus the name and
 * shelf price to show while Laravel's preview is on its way. Totals, VAT,
 * change and everything that is saved come from the server.
 */
export interface CartLine {
    article_id: number;
    quantite: number;
    designation: string;
    prix_vente: number;
}

interface PosState {
    lines: CartLine[];
    clientId: number | null;
    mode: PaymentMode;
    montantRecu: string;
    /** Idempotency key of the sale being built; renewed on every change. */
    cle: string;
    /** Last article added, for the flash on its line. */
    lastAdded: { id: number; at: number } | null;

    add: (article: Article) => void;
    setQuantite: (articleId: number, quantite: number) => void;
    plus: (articleId: number) => void;
    moins: (articleId: number) => void;
    retirer: (articleId: number) => void;
    vider: () => void;
    setClient: (id: number | null) => void;
    setMode: (mode: PaymentMode) => void;
    setMontantRecu: (value: string) => void;
    nouvelleVente: () => void;
}

const round2 = (n: number) => Math.round(n * 100) / 100;

export const usePos = create<PosState>((set) => ({
    lines: [],
    clientId: null,
    mode: 'especes',
    montantRecu: '',
    cle: uuid(),
    lastAdded: null,

    // Same rule as ScanCart::add(): the same article again bumps its line.
    add: (article) =>
        set((s) => {
            const existing = s.lines.find((l) => l.article_id === article.id);
            const lines = existing
                ? s.lines.map((l) => (l.article_id === article.id ? { ...l, quantite: round2(l.quantite + 1) } : l))
                : [...s.lines, { article_id: article.id, quantite: 1, designation: article.designation, prix_vente: article.prix_vente }];

            return { lines, cle: uuid(), lastAdded: { id: article.id, at: Date.now() } };
        }),

    // Zero or less removes the line, as on the Livewire till.
    setQuantite: (articleId, quantite) =>
        set((s) => ({
            lines: quantite > 0
                ? s.lines.map((l) => (l.article_id === articleId ? { ...l, quantite: round2(quantite) } : l))
                : s.lines.filter((l) => l.article_id !== articleId),
            cle: uuid(),
        })),

    plus: (articleId) =>
        set((s) => ({
            lines: s.lines.map((l) => (l.article_id === articleId ? { ...l, quantite: round2(l.quantite + 1) } : l)),
            cle: uuid(),
        })),

    moins: (articleId) =>
        set((s) => ({
            lines: s.lines.flatMap((l) => {
                if (l.article_id !== articleId) return [l];
                const quantite = round2(l.quantite - 1);
                return quantite > 0 ? [{ ...l, quantite }] : [];
            }),
            cle: uuid(),
        })),

    retirer: (articleId) => set((s) => ({ lines: s.lines.filter((l) => l.article_id !== articleId), cle: uuid() })),

    vider: () => set({ lines: [], montantRecu: '', cle: uuid() }),

    setClient: (clientId) => set({ clientId, cle: uuid() }),
    setMode: (mode) => set({ mode, cle: uuid() }),
    setMontantRecu: (montantRecu) => set({ montantRecu, cle: uuid() }),

    // Ready for the next customer (PointDeVente::enregistrerVente() resets the same fields).
    nouvelleVente: () => set({ lines: [], montantRecu: '', clientId: null, cle: uuid(), lastAdded: null }),
}));

export function cartItems(lines: CartLine[]): CartItemInput[] {
    return lines.map((l) => ({ article_id: l.article_id, quantite: l.quantite }));
}
