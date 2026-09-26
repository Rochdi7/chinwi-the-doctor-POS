@php
    /*
    | One template, two receipts, both for an 80mm thermal roll:
    |  - $payment set: the receipt of one payment (what was handed over on
    |    this visit, and what is still owed on the sale);
    |  - $payment null, $invoice set: the receipt of the whole sale (articles,
    |    total, every payment so far), served as a download.
    | The page height is sized to the content by InvoicePdf::receiptHeight().
    */
    $payment ??= null;
    $invoice ??= null;
    $invoice = $payment?->invoice ?? $invoice;
    $logo ??= null;

    $rtl = \App\Support\Locales::isRtl();
    $align = $rtl ? 'right' : 'left';
    $opposite = $rtl ? 'left' : 'right';
    $t = fn (string $k) => __('app.receipt.'.$k);
    $n = fn ($v) => number_format((float) $v, 2, ',', ' ');
    $qte = fn ($v) => number_format((float) $v, (float) $v == floor((float) $v) ? 0 : 2, ',', ' ');

    $items = $invoice?->items ?? collect();
    $payments = $payment ? collect() : ($invoice?->payments ?? collect())->sortBy('id')->values();
    $remise = $items->sum(fn ($i) => (float) $i->remise);
    $paye = (float) ($invoice?->montant_paye ?? $payment?->montant ?? 0);
    $reste = $invoice ? max((float) $invoice->total_ttc - $paye, 0) : 0;
    $solde = $invoice && $reste <= 0;

    $client = ($payment?->client ?? $invoice?->client)?->raison_sociale ?? __('app.vente.client_passage');
    $caissier = ($payment?->user ?? $invoice?->user)?->name;
    $date = $payment ? $payment->date_paiement : $invoice?->date_facture;
    $heure = ($payment ?? $invoice)?->created_at;
    $when = ($date ? $date->format('d/m/Y') : '').($heure ? ' '.$heure->format('H:i') : '');
    $titre = $payment ? $t('titre') : $t('titre_vente');
    $numero = $payment ? '#'.$payment->id : ($invoice?->numero ?? '');
@endphp
<!DOCTYPE html>
<html dir="{{ $rtl ? 'rtl' : 'ltr' }}" lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ $titre }} {{ $numero }}</title>
    <style>
        @page { margin: 4mm 3mm; }
        * { font-family: "{{ $rtl ? 'tajawal' : 'DejaVu Sans' }}", sans-serif; }
        body { font-size: 9px; color: #000; margin: 0; line-height: 1.35; }
        .center { text-align: center; }
        .logo { height: 34px; margin-bottom: 2px; }
        .societe { font-size: 13px; font-weight: bold; letter-spacing: 0.5px; }
        .small { font-size: 8px; line-height: 1.4; }
        .muted { color: #333; }
        .titre { font-size: 11px; font-weight: bold; letter-spacing: 1px; margin: 5px 0 3px; }
        .rule { border-top: 1.5px solid #000; margin: 5px 0; height: 0; }
        .sep { border-top: 1px dashed #000; margin: 5px 0; height: 0; }
        table { width: 100%; border-collapse: collapse; }
        td, th { padding: 1px 0; vertical-align: top; text-align: {{ $align }}; }
        th { font-size: 7.5px; font-weight: bold; letter-spacing: 0.5px; text-transform: uppercase; color: #333; padding-bottom: 2px; border-bottom: 1px solid #999; }
        td.num, th.num { text-align: {{ $opposite }}; white-space: nowrap; }
        .num, .ltr { direction: ltr; unicode-bidi: embed; }
        .k { color: #333; }
        .article { font-weight: bold; padding-top: 3px; }
        .detail td { font-size: 8px; color: #333; }
        .total td { font-size: 13px; font-weight: bold; padding: 4px 3px; border-top: 1.5px solid #000; border-bottom: 1.5px solid #000; }
        .bold td { font-weight: bold; }
        .big td { font-size: 11px; font-weight: bold; padding: 2px 0; }
        .solde { border: 1.5px solid #000; padding: 3px; margin-top: 4px; font-weight: bold; font-size: 10px; text-align: center; }
        .reste { font-size: 11px; }
        .foot { font-size: 8px; line-height: 1.45; }
        .merci { font-size: 9.5px; font-weight: bold; margin-top: 3px; }
    </style>
</head>
<body>

<div class="center">
    @if($logo)<img src="{{ $logo }}" class="logo" alt="{{ $societe['nom'] }}"><br>@endif
    <div class="societe">{{ $societe['nom'] }}</div>
    <div class="small muted">
        @if($societe['adresse']){!! nl2br(e($societe['adresse'])) !!}<br>@endif
        @if($societe['telephone']){{ __('app.pdf.tel') }} : <span class="ltr">{{ $societe['telephone'] }}</span><br>@endif
        @if($societe['ice'])ICE : <span class="ltr">{{ $societe['ice'] }}</span>@endif
    </div>
</div>

<div class="rule"></div>

<div class="center titre">{{ $titre }}</div>

<table class="small">
    <tr>
        <td><span class="k">{{ $payment ? $t('numero') : $t('facture') }}</span> <b class="ltr">{{ $numero }}</b></td>
        <td class="num">{{ $when }}</td>
    </tr>
    @if($payment && $invoice)
        <tr>
            <td colspan="2"><span class="k">{{ $t('facture') }}</span> <b class="ltr">{{ $invoice->numero }}</b></td>
        </tr>
    @endif
    <tr>
        <td colspan="2"><span class="k">{{ $t('client') }} :</span> <b>{{ $client }}</b></td>
    </tr>
    @if($caissier)
        <tr>
            <td colspan="2"><span class="k">{{ $t('caissier') }} :</span> {{ $caissier }}</td>
        </tr>
    @endif
</table>

@if($items->isNotEmpty())
    <div class="sep"></div>

    <table>
        <tr>
            <th>{{ $t('articles') }} ({{ $items->count() }})</th>
            <th class="num">{{ $devise }}</th>
        </tr>
        @foreach($items as $item)
            <tr>
                <td colspan="2" class="article">{{ $item->designation }}</td>
            </tr>
            <tr class="detail">
                <td>
                    <span class="num">{{ $qte($item->quantite) }} x {{ $n((float) $item->prix_unitaire * (1 + (float) $item->tva / 100)) }}</span>
                    @if((float) $item->remise > 0)
                        <span class="num">(−{{ $n($item->remise) }})</span>
                    @endif
                </td>
                <td class="num">{{ $n($item->total_ttc) }}</td>
            </tr>
        @endforeach
    </table>
@endif

@if($invoice)
    <div class="sep"></div>

    <table>
        @if($remise > 0)
            <tr>
                <td class="k">{{ $t('sous_total') }}</td>
                <td class="num">{{ $n((float) $invoice->total_ttc + $remise) }} {{ $devise }}</td>
            </tr>
            <tr>
                <td class="k">{{ $t('remise') }}</td>
                <td class="num">−{{ $n($remise) }} {{ $devise }}</td>
            </tr>
        @endif
        <tr class="total">
            <td>{{ $t('total') }}</td>
            <td class="num">{{ $n($invoice->total_ttc) }} {{ $devise }}</td>
        </tr>
    </table>
@endif

<div class="sep"></div>

<table>
    @if($payment)
        <tr class="big">
            <td>{{ $t('montant_paye') }}</td>
            <td class="num">{{ $n($payment->montant) }} {{ $devise }}</td>
        </tr>
        <tr>
            <td class="k">{{ $t('mode') }}</td>
            <td style="text-align: {{ $opposite }}">{{ __('app.mode.'.$payment->mode) }}</td>
        </tr>
        @if($payment->reference)
            <tr>
                <td class="k">{{ $t('reference') }}</td>
                <td style="text-align: {{ $opposite }}"><span class="ltr">{{ $payment->reference }}</span></td>
            </tr>
        @endif
    @else
        <tr>
            <th>{{ $t('paiements') }}</th>
            <th class="num">{{ $devise }}</th>
        </tr>
        @forelse($payments as $p)
            <tr>
                <td><span class="ltr">{{ $p->date_paiement?->format('d/m/Y') }}</span> · {{ __('app.mode.'.$p->mode) }}@if($p->reference) · <span class="ltr">{{ $p->reference }}</span>@endif</td>
                <td class="num">{{ $n($p->montant) }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="2" class="k">{{ $t('aucun_paiement') }}</td>
            </tr>
        @endforelse
    @endif
    @if($invoice)
        <tr class="bold">
            <td>{{ $t('deja_paye') }}</td>
            <td class="num">{{ $n($paye) }} {{ $devise }}</td>
        </tr>
        @if(! $solde)
            <tr class="bold reste">
                <td>{{ $t('reste') }}</td>
                <td class="num">{{ $n($reste) }} {{ $devise }}</td>
            </tr>
        @endif
    @endif
</table>

@if($solde)
    <div class="solde">{{ $t('solde') }}</div>
@endif

<div class="sep"></div>

<div class="center foot">
    <div>{{ $t('garder') }}</div>
    <div class="merci">{{ $t('merci') }}</div>
</div>

</body>
</html>
