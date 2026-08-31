@php
    $rtl = \App\Support\Locales::isRtl();
    $align = $rtl ? 'right' : 'left';
    $opposite = $rtl ? 'left' : 'right';
    $t = fn (string $k) => __('app.receipt.'.$k);
    $logo ??= null;
    $n = fn ($v) => number_format((float) $v, 2, ',', ' ').' '.$devise;
    $reste = max((float) $payment->invoice?->total_ttc - (float) $payment->invoice?->montant_paye, 0);
@endphp
<!DOCTYPE html>
<html dir="{{ $rtl ? 'rtl' : 'ltr' }}" lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ $t('titre') }}</title>
    <style>
        * { font-family: "{{ $rtl ? 'tajawal' : 'DejaVu Sans' }}", sans-serif; }
        body { font-size: 12px; color: #1f2937; margin: 0; }
        .societe { font-size: 17px; font-weight: bold; }
        .logo { height: 50px; margin-bottom: 6px; }
        .muted { color: #6b7280; font-size: 11px; line-height: 1.5; }
        .titre { font-size: 20px; font-weight: bold; }
        .box { border: 1px solid #d1d5db; padding: 10px; margin-top: 14px; }
        table.det { width: 100%; border-collapse: collapse; margin-top: 16px; }
        table.det td { padding: 7px 8px; border-bottom: 1px solid #e5e7eb; }
        .num { direction: ltr; text-align: {{ $opposite }}; }
        .paid td { background: #111827; color: #fff; font-size: 16px; font-weight: bold; border: 0; }
        .reste td { font-weight: bold; color: #b45309; }
        .solde td { font-weight: bold; color: #047857; }
        .foot { margin-top: 26px; font-size: 11px; color: #6b7280; }
        .ltr { direction: ltr; unicode-bidi: embed; }
    </style>
</head>
<body>

<table width="100%">
    <tr>
        <td width="55%" align="{{ $align }}">
            @if($logo)<img src="{{ $logo }}" class="logo" alt="{{ $societe['nom'] }}">@endif
            <div class="societe">{{ $societe['nom'] }}</div>
            <div class="muted">
                {!! nl2br(e($societe['adresse'])) !!}<br>
                @if($societe['telephone'])<span class="ltr">{{ $societe['telephone'] }}</span><br>@endif
                @if($societe['ice']) ICE: <span class="ltr">{{ $societe['ice'] }}</span>@endif
            </div>
        </td>
        <td width="45%" align="{{ $opposite }}">
            <div class="titre">{{ $t('titre') }}</div>
            <div class="muted">
                {{ $t('numero') }} <span class="ltr">{{ $payment->id }}</span><br>
                <span class="ltr">{{ $payment->date_paiement->format('d/m/Y') }}</span>
            </div>
        </td>
    </tr>
</table>

<div class="box">
    <strong>{{ $t('client') }} :</strong>
    {{ $payment->client?->raison_sociale ?? __('app.vente.client_passage') }}
    @if($payment->invoice)
        <br><span class="muted">{{ $t('facture') }} : <span class="ltr">{{ $payment->invoice->numero }}</span></span>
    @endif
</div>

<table class="det">
    <tr class="paid">
        <td>{{ $t('montant_paye') }}</td>
        <td class="num">{{ $n($payment->montant) }}</td>
    </tr>
    <tr>
        <td>{{ $t('mode') }}</td>
        <td class="num">{{ __('app.mode.'.$payment->mode) }}</td>
    </tr>
    @if($payment->reference)
        <tr>
            <td>{{ $t('reference') }}</td>
            <td class="num"><span class="ltr">{{ $payment->reference }}</span></td>
        </tr>
    @endif
    @if($payment->invoice)
        <tr>
            <td>{{ $t('total_facture') }}</td>
            <td class="num">{{ $n($payment->invoice->total_ttc) }}</td>
        </tr>
        <tr>
            <td>{{ $t('deja_paye') }}</td>
            <td class="num">{{ $n($payment->invoice->montant_paye) }}</td>
        </tr>
        @if($reste > 0)
            <tr class="reste">
                <td>{{ $t('reste') }}</td>
                <td class="num">{{ $n($reste) }}</td>
            </tr>
        @else
            <tr class="solde">
                <td colspan="2">{{ $t('solde') }}</td>
            </tr>
        @endif
    @endif
</table>

<div class="foot">{{ $t('merci') }}</div>

</body>
</html>
