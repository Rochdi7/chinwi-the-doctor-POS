import { useCallback, useEffect, useRef, useState } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { useT } from '@/auth/session';
import { api, ApiError, errorMessage } from '@/lib/api';
import { parseAmount } from '@/lib/format';
import { toast } from '@/components/ui/toast';
import { cartItems, usePos } from './store';
import { scanCode, useApercu, useArticles, usePosInit, useVente } from './queries';
import { useKeyboardScanner } from './hooks/useKeyboardScanner';
import { useUsbStatus } from './hooks/useUsbStatus';
import { PosHeader } from './components/PosHeader';
import { CategoryRail } from './components/CategoryRail';
import { ProductGrid } from './components/ProductGrid';
import { CartPanel } from './components/CartPanel';
import { ActionBar } from './components/ActionBar';
import { Calculator } from './components/Calculator';
import { SaleDoneDialog } from './components/SaleDoneDialog';
import type { Article, PosJournee, ScanResult, VenteResult } from '@/types/api';

const dialogOpen = () => document.querySelector('dialog[open]') !== null;

/**
 * The till: scan → cart → payment → Encaisser → receipt.
 *
 * Local state is only what the cashier picked. Everything that is money
 * (line totals, VAT, total, change) comes from Laravel's preview, and the
 * sale itself is written by SaleService behind POST /api/pos/ventes.
 */
export default function PosPage() {
    const t = useT();
    const queryClient = useQueryClient();

    // No scan box on screen: the USB scanner is heard page-wide (useKeyboardScanner),
    // so this ref stays empty and scans land wherever the cursor is.
    const scanBox = useRef<HTMLInputElement>(null);
    const searchBox = useRef<HTMLInputElement>(null);
    const amountBox = useRef<HTMLInputElement>(null);

    const [recherche, setRecherche] = useState('');
    const [categorie, setCategorie] = useState<number | null>(null);
    const [done, setDone] = useState<VenteResult | null>(null);
    const [calc, setCalc] = useState(false);

    // Today's figures: fetched once, refreshed after each sale.
    const journee = useQuery({ queryKey: ['pos', 'journee'], queryFn: () => api<PosJournee>('/pos/journee'), staleTime: 60_000 });

    const lines = usePos((s) => s.lines);
    const montantRecu = usePos((s) => s.montantRecu);

    const init = usePosInit();
    const articles = useArticles(recherche, categorie);
    const apercu = useApercu(lines, montantRecu);
    const vente = useVente();

    // Back to "nothing focused" so the next scan is not typed into a field.
    const focusScan = useCallback(() => window.setTimeout(() => (document.activeElement as HTMLElement | null)?.blur(), 0), []);

    /** Products, categories and today's figures again: after a delivery keyed in on another PC. */
    const refresh = useCallback(() => {
        void queryClient.invalidateQueries({ queryKey: ['pos'] });
        focusScan();
    }, [queryClient, focusScan]);

    // ---- Adding to the cart ------------------------------------------------

    const addArticle = useCallback((article: Article) => usePos.getState().add(article), []);

    /** A scan from the USB scanner or the scan box. */
    const handleScan = useCallback(
        (result: ScanResult) => {
            if (!result.article) {
                toast.error(result.message);
                return;
            }
            // A new scan while the last sale's receipt is up starts the next sale.
            setDone(null);
            addArticle(result.article);
            // Selling into empty stock is allowed at the till: warn, never block.
            if (result.stock_zero) toast.warning(result.message);
        },
        [addArticle],
    );

    const onKeyboardScan = useCallback(
        async (code: string) => {
            try {
                handleScan(await scanCode(code));
            } catch (error) {
                if (error instanceof ApiError && error.kind === 'notfound' && error.data) {
                    handleScan(error.data as ScanResult);
                } else {
                    toast.error(errorMessage(error, t));
                }
            }
        },
        [handleScan, t],
    );

    useKeyboardScanner(scanBox, onKeyboardScan);
    const usb = useUsbStatus(init.data?.usb);

    // ---- Saving the sale ---------------------------------------------------

    const submit = useCallback(
        (encaisser: boolean) => {
            const s = usePos.getState();
            if (s.lines.length === 0 || vente.isPending) return;

            const montant = parseAmount(s.montantRecu);
            if (s.montantRecu.trim() !== '' && montant === null) {
                toast.error(t('spa.erreur.validation'));
                amountBox.current?.focus();
                return;
            }

            vente.mutate(
                { items: cartItems(s.lines), client_id: s.clientId, mode: s.mode, encaisser, montant_recu: montant, cle: s.cle },
                {
                    onSuccess: (result) => {
                        usePos.getState().nouvelleVente();
                        setDone(result);
                        // Stock moved and the day's figures too.
                        void queryClient.invalidateQueries({ queryKey: ['pos', 'articles'] });
                        void queryClient.invalidateQueries({ queryKey: ['pos', 'journee'] });
                    },
                    onError: (error) => toast.error(errorMessage(error, t)),
                },
            );
        },
        [vente, queryClient, t],
    );

    // ---- Keyboard shortcuts (F-keys: never typed by a scanner) -------------

    useEffect(() => {
        const onKeyDown = (e: KeyboardEvent) => {
            if (dialogOpen()) return;

            if (e.key === 'F2') {
                e.preventDefault();
                searchBox.current?.focus();
                searchBox.current?.select();
            } else if (e.key === 'F4') {
                e.preventDefault();
                amountBox.current?.focus();
                amountBox.current?.select();
            } else if (e.key === 'F9') {
                e.preventDefault();
                submit(true);
            } else if (e.key === 'Escape') {
                if (document.activeElement === searchBox.current && recherche !== '') setRecherche('');
                (document.activeElement as HTMLElement | null)?.blur();
            }
        };

        window.addEventListener('keydown', onKeyDown);
        return () => window.removeEventListener('keydown', onKeyDown);
    }, [submit, recherche]);

    useEffect(() => {
        if (init.isError) toast.error(errorMessage(init.error, t));
    }, [init.isError, init.error, t]);

    const empty = lines.length === 0;

    return (
        <div className="flex h-dvh flex-col bg-canvas">
            <PosHeader
                usb={usb}
                journee={journee.data}
                onCalculator={() => setCalc(true)}
                onRefresh={refresh}
                refreshing={articles.isFetching || init.isFetching}
            />

            <main className="grid min-h-0 flex-1 grid-cols-1 gap-3 overflow-y-auto p-3 lg:grid-cols-[6.5rem_minmax(0,1fr)_minmax(22rem,25rem)] lg:overflow-hidden xl:grid-cols-[7rem_minmax(0,1fr)_27rem]">
                <CategoryRail categories={init.data?.categories ?? []} active={categorie} onSelect={setCategorie} />

                <div className="flex min-h-[60dvh] flex-col lg:min-h-0">
                    <ProductGrid
                        articles={articles.data}
                        categories={init.data?.categories ?? []}
                        loading={articles.isPending}
                        refreshing={articles.isFetching && articles.isPlaceholderData}
                        recherche={recherche}
                        onRecherche={setRecherche}
                        searchBox={searchBox}
                        onAdd={addArticle}
                    />
                </div>

                <CartPanel
                    apercu={apercu.data}
                    apercuStale={apercu.isFetching || apercu.isPlaceholderData}
                    clients={init.data?.clients ?? []}
                    amountBox={amountBox}
                />
            </main>

            <ActionBar total={empty ? undefined : apercu.data?.total_ttc} busy={vente.isPending} onEncaisser={() => submit(true)} onEnregistrer={() => submit(false)} />

            <SaleDoneDialog result={done} onClose={() => { setDone(null); focusScan(); }} />
            <Calculator open={calc} onClose={() => { setCalc(false); focusScan(); }} onUse={(v) => { usePos.getState().setMontantRecu(String(v).replace('.', ',')); amountBox.current?.focus(); }} />
        </div>
    );
}
