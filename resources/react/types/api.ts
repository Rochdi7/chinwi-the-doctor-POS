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

// ---- Back office ----------------------------------------------------------

/** Laravel's LengthAwarePaginator as JSON. */
export interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
}

export interface ArticleRow {
    id: number;
    designation: string;
    reference: string;
    code_barre: string | null;
    code_barre_standard: boolean | null;
    unite: string | null;
    unite_label: string;
    marque: string | null;
    category_id: number | null;
    category: string | null;
    prix_achat: number;
    prix_vente: number;
    stock: number;
    tva: number;
    actif: boolean;
    label_url: string | null;
    barcode_url: string | null;
}

export interface ArticleDefaults {
    reference: string;
    code_barre: string;
    unite: string;
    unites: Record<string, string>;
}

export interface CategoryRow {
    id: number;
    nom: string;
    articles_count: number;
}

export interface ClientRow {
    id: number;
    raison_sociale: string;
    telephone: string | null;
    email: string | null;
    adresse: string | null;
    ice: string | null;
    rc: string | null;
    numero_compte: string | null;
    solde: number;
    actif: boolean;
}

export interface ClientDetail extends ClientRow {
    invoices: { id: number; numero: string; date: string | null; statut: InvoiceStatut; total_ttc: number; reste: number }[];
    payments: { id: number; date: string | null; numero: string | null; mode: PaymentMode; montant: number; pdf_url: string }[];
}

export interface InvoiceRow {
    id: number;
    numero: string;
    date_facture: string | null;
    client_id: number | null;
    client: string | null;
    statut: InvoiceStatut;
    total_ttc: number;
    montant_paye: number;
    reste: number;
    pdf_url: string;
}

export interface InvoiceLine {
    id?: number;
    article_id: number | null;
    designation: string;
    quantite: number;
    prix_unitaire: number;
    remise: number;
    tva: number;
    total_ht?: number;
    total_ttc?: number;
}

export interface InvoicePayment {
    id: number;
    date: string | null;
    mode: PaymentMode;
    reference: string | null;
    montant: number;
    pdf_url: string;
}

export interface InvoiceDetail extends InvoiceRow {
    note: string | null;
    total_ht: number;
    total_tva: number;
    user: string | null;
    items: InvoiceLine[];
    payments: InvoicePayment[];
}

export interface PaymentRow {
    id: number;
    date_paiement: string | null;
    client_id: number | null;
    client: string | null;
    invoice_id: number | null;
    numero: string | null;
    montant: number;
    mode: PaymentMode;
    reference: string | null;
    pdf_url: string;
}

export interface CaisseRow {
    id: number;
    occurred_at: string | null;
    type: 'entree' | 'sortie';
    montant: number;
    solde_avant: number;
    solde_apres: number;
    motif: string;
    user: string | null;
    payment_id: number | null;
}

export interface AuditChange {
    champ: string;
    avant: string;
    apres: string;
    delta: string | null;
}

export interface JournalRow {
    id: number;
    occurred_at: string | null;
    user: string | null;
    event: string;
    subject_type: string | null;
    subject_id: number | null;
    description: string | null;
    montant: number | null;
    ip: string | null;
    changes: AuditChange[];
    remise: string | null;
}

export interface Dashboard {
    stats: { ca: number; regle: number; impaye: number; caisse: number };
    mensuel: { labels: string[]; facture: number[]; encaisse: number[] };
    statuts: { statut: InvoiceStatut; count: number }[];
    top_clients: { id: number; nom: string; total: number }[];
}

export type DashboardKey = 'ca' | 'regle' | 'impaye' | 'caisse';

export interface DashboardDetail {
    total: number;
    count: number;
    rows: Record<string, string | number | null>[];
}

export interface Settings {
    societe_nom: string | null;
    societe_adresse: string | null;
    societe_telephone: string | null;
    societe_email: string | null;
    societe_ice: string | null;
    societe_rc: string | null;
    devise: string | null;
    tva_defaut: string | number | null;
}
