@php
    $rtl = \App\Support\Locales::isRtl();
    $align = $rtl ? 'right' : 'left';
    $opposite = $rtl ? 'left' : 'right';
    $t = fn (string $k) => __('app.pdf.'.$k);
    $logo ??= null;
@endphp
<!DOCTYPE html>
<html dir="{{ $rtl ? 'rtl' : 'ltr' }}" lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ $t('titre') }} {{ $invoice->numero }}</title>
    <style>
        * { font-family: "DejaVu Sans", sans-serif; }
        body { font-size: 12px; color: #1f2937; margin: 0; direction: {{ $rtl ? 'rtl' : 'ltr' }}; }
        .head { width: 100%; margin-bottom: 24px; }
        .head td { vertical-align: top; text-align: {{ $align }}; }
        .societe { font-size: 18px; font-weight: bold; }
        .logo { height: 60px; margin-bottom: 8px; }
        .muted { color: #6b7280; font-size: 11px; line-height: 1.5; }
        .titre { font-size: 22px; font-weight: bold; text-align: {{ $opposite }}; }
        .meta { text-align: {{ $opposite }}; }
        .box { border: 1px solid #d1d5db; padding: 10px; text-align: {{ $align }}; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 20px; }
        table.items th { background: #111827; color: #fff; padding: 8px; font-size: 11px; text-align: {{ $align }}; }
        table.items td { border-bottom: 1px solid #e5e7eb; padding: 8px; text-align: {{ $align }}; }
        /* Amounts stay LTR in Latin digits so figures never reorder. */
        .num { text-align: {{ $opposite }}; direction: ltr; }
        table.tot { width: 48%; margin-{{ $align }}: auto; margin-top: 16px; border-collapse: collapse; }
        table.tot td { padding: 6px 8px; border-bottom: 1px solid #e5e7eb; text-align: {{ $align }}; }
        table.tot td.num { text-align: {{ $opposite }}; }
        table.tot tr.grand td { background: #111827; color: #fff; font-size: 15px; font-weight: bold; border: 0; }
        .rem { color: #b45309; }
        .foot { position: fixed; bottom: 0; left: 0; right: 0; text-align: center; font-size: 10px; color: #9ca3af; }
    </style>
</head>
<body>
<table class="head">
    <tr>
        <td width="55%">
            @if($logo)<img src="{{ $logo }}" class="logo" alt="{{ $societe['nom'] }}">@endif
            <div class="societe">{{ $societe['nom'] }}</div>
            <div class="muted">
                {!! nl2br(e($societe['adresse'])) !!}<br>
                @if($societe['telephone']) {{ $t('tel') }}: <span dir="ltr">{{ $societe['telephone'] }}</span><br>@endif
                @if($societe['email']) <span dir="ltr">{{ $societe['email'] }}</span><br>@endif
                @if($societe['ice']) ICE: <span dir="ltr">{{ $societe['ice'] }}</span> @endif
                @if($societe['rc']) — RC: <span dir="ltr">{{ $societe['rc'] }}</span> @endif
            </div>
        </td>
        <td width="45%">
            <div class="titre">{{ $t('titre') }}</div>
            <div class="muted meta">
                {{ $t('numero') }} <span dir="ltr">{{ $invoice->numero }}</span><br>
                <span dir="ltr">{{ $invoice->date_facture->format('d/m/Y') }}</span>
                @if($invoice->bc_client)<br>BC: <span dir="ltr">{{ $invoice->bc_client }}</span>@endif
            </div>
        </td>
    </tr>
</table>

<div class="box">
    <strong>{{ $t('client') }} :</strong> {{ $invoice->clientNom() }}<br>
    @if($invoice->client)
        <span class="muted">
            {!! nl2br(e($invoice->client->adresse)) !!}
            @if($invoice->client->telephone)<br>{{ $t('tel') }}: <span dir="ltr">{{ $invoice->client->telephone }}</span>@endif
            @if($invoice->client->ice)<br>ICE: <span dir="ltr">{{ $invoice->client->ice }}</span>@endif
        </span>
    @endif
</div>

<table class="items">
    <thead>
    <tr>
        <th>{{ $t('designation') }}</th>
        <th class="num">{{ $t('qte') }}</th>
        <th class="num">{{ $t('pu') }}</th>
        <th class="num">{{ $t('remise') }}</th>
        <th class="num">{{ $t('total_ht') }}</th>
        <th class="num">{{ $t('tva') }}</th>
        <th class="num">{{ $t('total_ttc') }}</th>
    </tr>
    </thead>
    <tbody>
    @foreach($invoice->items as $item)
        <tr>
            <td>{{ $item->designation }}</td>
            <td class="num">{{ number_format((float) $item->quantite, (float) $item->quantite == floor((float) $item->quantite) ? 0 : 2, ',', ' ') }}</td>
            <td class="num">{{ number_format((float) $item->prix_unitaire, 2, ",", " ") }}</td>
            <td class="num rem">
                @if((float) $item->remise > 0) −{{ number_format((float) $item->remise, 2, ",", " ") }} @else — @endif
            </td>
            <td class="num">{{ number_format((float) $item->total_ht, 2, ",", " ") }}</td>
            <td class="num">{{ (float) $item->tva }}%</td>
            <td class="num">{{ number_format((float) $item->total_ttc, 2, ",", " ") }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

@php($remiseTotale = $invoice->items->sum(fn ($i) => (float) $i->remise))

<table class="tot">
    <tr>
        <td>{{ $t('sous_total') }}</td>
        <td class="num">{{ number_format((float) $invoice->total_ht + $remiseTotale, 2, ",", " ") }} {{ $devise }}</td>
    </tr>
    @if($remiseTotale > 0)
        <tr class="rem">
            <td>{{ $t('remise') }}</td>
            <td class="num">−{{ number_format($remiseTotale, 2, ",", " ") }} {{ $devise }}</td>
        </tr>
    @endif
    <tr><td>{{ $t('total_ht') }}</td><td class="num">{{ number_format((float) $invoice->total_ht, 2, ",", " ") }} {{ $devise }}</td></tr>
    <tr><td>{{ $t('tva') }}</td><td class="num">{{ number_format((float) $invoice->total_tva, 2, ",", " ") }} {{ $devise }}</td></tr>
    <tr class="grand"><td>{{ $t('grand_total') }}</td><td class="num">{{ number_format((float) $invoice->total_ttc, 2, ",", " ") }} {{ $devise }}</td></tr>
    <tr><td>{{ $t('paye') }}</td><td class="num">{{ number_format((float) $invoice->montant_paye, 2, ",", " ") }} {{ $devise }}</td></tr>
    <tr><td><strong>{{ $t('reste') }}</strong></td><td class="num"><strong>{{ number_format((float) $invoice->total_ttc - (float) $invoice->montant_paye, 2, ",", " ") }} {{ $devise }}</strong></td></tr>
</table>

@if($invoice->note)
    <p class="muted">{{ $invoice->note }}</p>
@endif

<div class="foot">{{ $societe['nom'] }} — <span dir="ltr">{{ $invoice->numero }}</span></div>
</body>
</html>
