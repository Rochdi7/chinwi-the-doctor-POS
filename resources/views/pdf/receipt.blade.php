@php
    $rtl = \App\Support\Locales::isRtl();
    $align = $rtl ? 'right' : 'left';
    $opposite = $rtl ? 'left' : 'right';
    $t = fn (string $k) => __('app.receipt.'.$k);
    $logo ??= null;
    $n = fn ($v) => number_format((float) $v, 2, ',', ' ');
    $qte = fn ($v) => number_format((float) $v, (float) $v == floor((float) $v) ? 0 : 2, ',', ' ');
    $invoice = $payment->invoice;
    $items = $invoice?->items ?? collect();
    $remise = $items->sum(fn ($i) => (float) $i->remise);
    $reste = max((float) $invoice?->total_ttc - (float) $invoice?->montant_paye, 0);
@endphp
<!DOCTYPE html>
<html dir="{{ $rtl ? 'rtl' : 'ltr' }}" lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ $t('titre') }}</title>
    <style>
        {{-- 80mm thermal roll: the page height is sized to the content by InvoicePdf::receipt(). --}}
        @page { margin: 4mm 3mm; }
        * { font-family: "{{ $rtl ? 'tajawal' : 'DejaVu Sans' }}", sans-serif; }
        body { font-size: 9px; color: #000; margin: 0; }
        .center { text-align: center; }
        .logo { height: 34px; margin-bottom: 3px; }
        .societe { font-size: 12px; font-weight: bold; }
        .small { font-size: 8px; line-height: 1.4; }
        .titre { font-size: 11px; font-weight: bold; margin-top: 4px; }
        .sep { border-top: 1px dashed #000; margin: 5px 0; height: 0; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 1px 0; vertical-align: top; text-align: {{ $align }}; }
        td.num { text-align: {{ $opposite }}; white-space: nowrap; }
        .num, .ltr { direction: ltr; unicode-bidi: embed; }
        .article { font-weight: bold; padding-top: 3px; }
        .detail td { font-size: 8px; }
        .total td { font-size: 13px; font-weight: bold; padding: 3px 0; }
        .bold td { font-weight: bold; }
    </style>
</head>
<body>

<div class="center">
    @if($logo)<img src="{{ $logo }}" class="logo" alt="{{ $societe['nom'] }}"><br>@endif
    <div class="societe">{{ $societe['nom'] }}</div>
    <div class="small">
        @if($societe['adresse']){!! nl2br(e($societe['adresse'])) !!}<br>@endif
        @if($societe['telephone'])<span class="ltr">{{ $societe['telephone'] }}</span><br>@endif
        @if($societe['ice'])ICE: <span class="ltr">{{ $societe['ice'] }}</span>@endif
    </div>
    <div class="titre">{{ $t('titre') }}</div>
</div>

<div class="sep"></div>

<table class="small">
    <tr>
        <td>{{ $t('numero') }} <span class="ltr">{{ $payment->id }}</span></td>
        <td class="num">{{ $payment->date_paiement->format('d/m/Y') }}{{ $payment->created_at ? ' '.$payment->created_at->format('H:i') : '' }}</td>
    </tr>
    @if($invoice)
        <tr>
            <td colspan="2">{{ $t('facture') }} : <span class="ltr">{{ $invoice->numero }}</span></td>
        </tr>
    @endif
    <tr>
        <td colspan="2">{{ $t('client') }} : {{ $payment->client?->raison_sociale ?? __('app.vente.client_passage') }}</td>
    </tr>
</table>

@if($items->isNotEmpty())
    <div class="sep"></div>

    <table>
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
                <td>{{ __('app.invoice.remise') }}</td>
                <td class="num">−{{ $n($remise) }}</td>
            </tr>
        @endif
        <tr class="total">
            <td>{{ __('app.invoice.total_ttc') }}</td>
            <td class="num">{{ $n($invoice->total_ttc) }} {{ $devise }}</td>
        </tr>
    </table>
@endif

<div class="sep"></div>

<table>
    <tr class="bold">
        <td>{{ $t('montant_paye') }}</td>
        <td class="num">{{ $n($payment->montant) }} {{ $devise }}</td>
    </tr>
    <tr>
        <td>{{ $t('mode') }}</td>
        <td class="num">{{ __('app.mode.'.$payment->mode) }}</td>
    </tr>
    @if($payment->reference)
        <tr>
            <td>{{ $t('reference') }}</td>
            <td class="num">{{ $payment->reference }}</td>
        </tr>
    @endif
    @if($invoice)
        <tr>
            <td>{{ $t('deja_paye') }}</td>
            <td class="num">{{ $n($invoice->montant_paye) }} {{ $devise }}</td>
        </tr>
        @if($reste > 0)
            <tr class="bold">
                <td>{{ $t('reste') }}</td>
                <td class="num">{{ $n($reste) }} {{ $devise }}</td>
            </tr>
        @else
            <tr class="bold">
                <td colspan="2" class="center">*** {{ $t('solde') }} ***</td>
            </tr>
        @endif
    @endif
</table>

<div class="sep"></div>

<div class="center small">{{ $t('merci') }}</div>

</body>
</html>
