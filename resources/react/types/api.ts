/**
 * Shapes returned by the Laravel API (routes/web.php, prefix /api).
 * Amounts are numbers computed by Laravel; the client only displays them.
 */

export interface User {
    id: number;
    name: string;
    email: string;
}

export interface LocaleOption {
    code: string;
    label: string;
}

/** lang/{locale}/app.php, as served by the session endpoint. */
export interface Messages {
    [key: string]: string | Messages;
}

export interface Session {
    user: User | null;
    locale: string;
    dir: 'ltr' | 'rtl';
    locales: LocaleOption[];
    societe: string | null;
    devise: string;
    messages: Messages;
}

export interface Category {
    id: number;
    nom: string;
}

export interface ClientOption {
    id: number;
    raison_sociale: string;
}

/** App\Support\ScannerUsb::status() */
export type UsbState = 'connected' | 'absent' | 'serie' | 'erreur' | 'unknown';

export interface UsbStatus {
    state: UsbState;
    name: string | null;
    label: string | null;
    aide: string | null;
}

export interface PosInit {
    categories: Category[];
    clients: ClientOption[];
    till: string;
    scanner_url: string;
    usb: UsbStatus;
}

export interface Article {
    id: number;
    designation: string;
    reference: string;
    code_barre: string | null;
    prix_vente: number;
    tva: number;
    stock: number;
    unite: string | null;
    unite_label: string;
    category_id: number | null;
}

export interface ScanResult {
    code: string;
    article: Article | null;
    message: string;
    stock_zero: boolean;
}

export interface ScansPoll {
    scans: ScanResult[];
    usb: UsbStatus;
}

export type PaymentMode = 'especes' | 'tpe';

export type InvoiceStatut = 'validee' | 'partielle' | 'payee';

/** What the cart sends: ids and quantities only. */
export interface CartItemInput {
    article_id: number;
    quantite: number;
}

export interface ApercuLine {
    article_id: number;
    designation: string;
    quantite: number;
    prix_unitaire: number;
    remise: number;
    tva: number;
    total_ht: number;
    total_ttc: number;
}

export interface Rendu {
    montant_encaisse: number;
    monnaie: number;
    reste: number;
}

export interface Apercu {
    lignes: ApercuLine[];
    total_ht: number;
    total_tva: number;
    total_ttc: number;
    rendu: Rendu;
    avertissements: { article_id: number; message: string }[];
}

export interface VenteInput {
    items: CartItemInput[];
    client_id: number | null;
    mode: PaymentMode;
    encaisser: boolean;
    montant_recu: number | null;
    cle: string;
}

export interface VenteResult {
    message: string;
    invoice: {
        id: number;
        numero: string;
        total_ttc: number;
        montant_paye: number;
        statut: InvoiceStatut;
        pdf_url: string;
    };
    payment: {
        id: number;
        montant: number;
        mode: PaymentMode;
        pdf_url: string;
    } | null;
    monnaie: number;
}
