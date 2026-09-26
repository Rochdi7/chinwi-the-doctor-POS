import { keepPreviousData, useMutation, useQuery } from '@tanstack/react-query';
import { api } from '@/lib/api';
import { parseAmount } from '@/lib/format';
import { useDebounced } from '@/lib/useDebounced';
import { cartItems, type CartLine } from './store';
import type { Apercu, Article, PosInit, ScanResult, VenteInput, VenteResult } from '@/types/api';

/** Categories, clients, till code and scanner state, once per visit. */
export function usePosInit() {
    return useQuery({
        queryKey: ['pos', 'init'],
        queryFn: () => api<PosInit>('/pos/init'),
        staleTime: 5 * 60_000,
    });
}

/** The grid: filtered on the server, like the Livewire grid. */
export function useArticles(recherche: string, categorie: number | null) {
    const search = useDebounced(recherche.trim(), 250);

    return useQuery<Article[]>({
        queryKey: ['pos', 'articles', search, categorie],
        queryFn: ({ signal }) =>
            api<{ data: Article[] }>('/pos/articles', { query: { recherche: search, categorie }, signal }).then((r) => r.data),
        placeholderData: keepPreviousData,
        staleTime: 30_000,
    });
}

/**
 * Totals, line totals and change for the cart as it is now, computed by
 * Laravel with the same code that saves the sale (SaleService::apercu()).
 */
export function useApercu(lines: CartLine[], montantRecu: string) {
    // Debounce a string, not an object: a new object every render would
    // never settle.
    const body = useDebounced(
        JSON.stringify({ items: cartItems(lines), montant_recu: parseAmount(montantRecu) }),
        120,
    );

    return useQuery<Apercu>({
        queryKey: ['pos', 'apercu', body],
        queryFn: ({ signal }) => api<Apercu>('/pos/apercu', { method: 'POST', body: JSON.parse(body) as unknown, signal }),
        // Both the live cart and the debounced copy must have lines: right
        // after the first scan the copy can still be the empty cart.
        enabled: lines.length > 0 && !body.startsWith('{"items":[]'),
        placeholderData: keepPreviousData,
        staleTime: 10_000,
    });
}

export function scanCode(code: string): Promise<ScanResult> {
    return api<ScanResult>('/pos/scan', { method: 'POST', body: { code } });
}

export function useVente() {
    return useMutation({
        mutationFn: (input: VenteInput) => api<VenteResult>('/pos/ventes', { method: 'POST', body: input }),
    });
}
