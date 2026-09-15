<div align="center">

<img src="public/assets/chinwi-the-doctor.jpeg" alt="Chinwi The Doctor" width="180" />

# Chinwi The Doctor — POS

**Point de vente & gestion commerciale** — facturation, stock, caisse et audit.
Trilingue : Français · العربية · الدارجة المغربية.

[![Laravel](https://img.shields.io/badge/Laravel-12-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)](https://laravel.com)
[![Filament](https://img.shields.io/badge/Filament-3.2-FDAE4B?style=for-the-badge&logo=laravel&logoColor=white)](https://filamentphp.com)
[![PHP](https://img.shields.io/badge/PHP-8.2+-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://php.net)
[![Tailwind](https://img.shields.io/badge/Tailwind-4.0-06B6D4?style=for-the-badge&logo=tailwindcss&logoColor=white)](https://tailwindcss.com)
[![License](https://img.shields.io/badge/License-MIT-22C55E?style=for-the-badge)](#-license)

</div>

---

## 📖 À propos

**Chinwi The Doctor POS** est une application de gestion commerciale complète, conçue pour un
commerce de détail marocain. Elle couvre tout le cycle de vente : de l'article scanné au
comptoir jusqu'à la facture PDF, le règlement, le mouvement de caisse et la trace d'audit.

L'interface d'administration est bâtie sur **Filament 3**, entièrement traduite et
compatible **RTL** pour l'arabe et la darija.

---

## ✨ Fonctionnalités

### 🧾 Facturation
- Numérotation automatique par année (`2026-0001`, `2026-0002`, …)
- Lignes de facture avec remise, TVA paramétrable et totaux HT / TVA / TTC
- Cycle de vie du statut : `validée` → `partielle` → `payée`
- Client optionnel — vente rapide au comptoir sans saisie
- **Facture PDF** en français (DomPDF) et en arabe/darija (mPDF, rendu RTL correct)
- Reçu de règlement PDF pour chaque paiement

### 📦 Articles & stock
- Catalogue avec catégories, prix d'achat / de vente et **15 unités** (Kg, L, Carton, Douzaine…)
- **Codes-barres EAN-13** générés automatiquement, préfixe `2` (plage GS1 réservée à
  l'usage interne — jamais de collision avec un code fabricant)
- Impression du code-barre en PNG / SVG
- Décrémentation du stock à la vente, avec **alerte immédiate** dès qu'un article tombe à zéro
- Contrôle d'intégrité du stock couvert par les tests

### 💰 Caisse
- Caisse unique partagée, protégée par verrou de ligne (`lockForUpdate`) en transaction
- Mouvements `entrée` / `sortie` avec motif, solde avant/après et lien vers le règlement
- Historique complet et non modifiable des mouvements

### 👥 Clients & règlements
- Fiches clients avec historique de facturation
- Paiements partiels ou complets, alimentant automatiquement la caisse

### 🔒 Sécurité & audit
- **Journal d'activité** (`activity_logs`) sur les opérations sensibles
- Vue **diff** avant/après pour chaque modification enregistrée
- Authentification obligatoire sur les routes PDF et code-barres

### 📊 Tableau de bord
- Vue d'ensemble : chiffre d'affaires, factures, caisse
- Graphique du **CA mensuel**
- Répartition par **statut** de facture
- **Top clients**

### 🌍 Multilingue
| Locale | Langue | Sens |
|:--|:--|:--|
| `fr` | Français | LTR |
| `ar` | العربية | RTL |
| `ary` | الدارجة المغربية | RTL |

Changement de langue depuis le menu utilisateur, sans rechargement de session.
Les PDF suivent automatiquement le moteur adapté à la langue choisie.

---

## 🛠️ Stack technique

| Couche | Technologie |
|:--|:--|
| Framework | Laravel 12 · PHP 8.2+ |
| Admin UI | Filament 3.2 (thème Emerald, police Cairo) |
| Front | Vite 7 · Tailwind CSS 4 |
| PDF | barryvdh/laravel-dompdf · mpdf/mpdf (arabe) |
| Codes-barres | picqer/php-barcode-generator |
| Base de données | SQLite (par défaut) · MySQL / PostgreSQL compatibles |
| Tests | PHPUnit 11 |

---

## 🚀 Installation

**Prérequis :** PHP ≥ 8.2, Composer, Node.js ≥ 18

```bash
git clone https://github.com/Rochdi7/chinwi-the-doctor-POS.git
cd chinwi-the-doctor-POS

# Installation complète en une commande
composer setup
```

Le script `composer setup` enchaîne : `composer install`, création du `.env`,
`key:generate`, `migrate`, `npm install` et `npm run build`.

### Données de départ

```bash
php artisan db:seed
```

Cela crée le compte administrateur et les paramètres société par défaut
(devise `DH`, TVA `20 %`).

| Champ | Valeur |
|:--|:--|
| Email | `admin@local.test` |
| Mot de passe | `admin1234` |

> ⚠️ **Changez ce mot de passe avant toute mise en production.**

### Lancer le projet

```bash
composer dev
```

Démarre simultanément le serveur, la file d'attente, les logs (Pail) et Vite.
L'application est ensuite disponible sur **http://localhost:8000/admin**.

---

## 🧪 Tests

```bash
composer test
```

La suite couvre notamment : intégrité du stock, vente avec paiement, audit de caisse,
génération et lecture de codes-barres EAN-13, rendu arabe de l'interface et des factures,
et cohérence des traductions.

---

## ⚙️ Configuration

Les informations de la société (nom, adresse, téléphone, email, **ICE**, **RC**, devise,
TVA par défaut) se règlent directement depuis la page **Paramètres** de l'interface
d'administration — elles alimentent l'en-tête des factures PDF et le nom affiché du panneau.

Le logo affiché dans le panneau et sur les factures est lu depuis :

```
public/assets/chinwi-the-doctor.jpeg
```

### ⚡ Performance (caisse)

Le point de vente fait un aller-retour serveur par scan. Deux réglages font
passer ce temps de ~500 ms à ~150 ms :

1. **OPcache** dans `php.ini` (sous XAMPP : `C:\xampp\php\php.ini`, puis
   redémarrer Apache) :

   ```ini
   zend_extension=opcache
   opcache.enable=1
   opcache.enable_cli=1
   ```

2. **Caches de production** après chaque déploiement :

   ```bash
   php artisan optimize && php artisan filament:optimize
   ```

---

## 📂 Structure

```
app/
├── Filament/
│   ├── Resources/     # Articles, Clients, Factures, Règlements, Caisse, Journal
│   ├── Pages/         # Paramètres
│   └── Widgets/       # Stats, CA mensuel, Statuts, Top clients
├── Http/Controllers/  # PDF facture, reçu, code-barres
├── Models/            # Article, Invoice, Payment, Caisse, ActivityLog…
└── Support/           # Barcode, InvoicePdf, Money, Units, Locales, StockAlert, AuditDiff
lang/                  # fr · ar · ary
resources/views/pdf/   # Gabarits facture (FR / AR) et reçu
```

---

## 📄 License

Distribué sous licence **MIT**.

<div align="center">

**Chinwi The Doctor POS** — développé avec ❤️ par [Rochdi](https://github.com/Rochdi7)

</div>
