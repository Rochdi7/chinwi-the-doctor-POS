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

    const scanBox = useRef<HTMLInputElement>(null);
    const searchBox = useRef<HTMLInputElement>(null);
    const amountBox = useRef<HTMLInputElement>(null);

    const [scan, setScan] = useState('');
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

    const focusScan = useCallback(() => window.setTimeout(() => scanBox.current?.focus(), 0), []);

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
            setScan('');
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
                scanBox.current?.focus();
            }
        };

        window.addEventListener('keydown', onKeyDown);
        return () => window.removeEventListener('keydown', onKeyDown);
    }, [submit, recherche]);

    useEffect(() => {
        if (init.isError) toast.error(errorMessage(init.error, t));
    }, [init.isError, init.error, t]);

    return (
        <div className="flex h-dvh flex-col">
            <PosHeader scanBox={scanBox} scan={scan} onScanChange={setScan} usb={usb} journee={journee.data} onCalculator={() => setCalc(true)} />

            <main className="grid min-h-0 flex-1 grid-cols-1 gap-3 overflow-y-auto p-3 lg:grid-cols-[10.5rem_minmax(0,1fr)_minmax(22rem,25rem)] lg:overflow-hidden xl:grid-cols-[12rem_minmax(0,1fr)_27rem]">
                <CategoryRail categories={init.data?.categories ?? []} active={categorie} onSelect={setCategorie} />

                <div className="flex min-h-[60dvh] flex-col lg:min-h-0">
                    <ProductGrid
                        articles={articles.data}
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
                    busy={vente.isPending}
                    onEncaisser={() => submit(true)}
                    onEnregistrer={() => submit(false)}
                    amountBox={amountBox}
                />
            </main>


            <SaleDoneDialog result={done} onClose={() => { setDone(null); focusScan(); }} />
            <Calculator open={calc} onClose={() => { setCalc(false); focusScan(); }} onUse={(v) => { usePos.getState().setMontantRecu(String(v).replace('.', ',')); amountBox.current?.focus(); }} />
        </div>
    );
}
