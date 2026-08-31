@php
    $t = fn (string $k) => __('app.pdf.'.$k);
    $logo ??= null;
    $n = fn ($v, int $d = 2) => number_format((float) $v, $d, ',', ' ');
    $remiseTotale = $invoice->items->sum(fn ($i) => (float) $i->remise);
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $t('titre') }} {{ $invoice->numero }}</title>
    <style>
        body { font-family: tajawal; font-size: 12px; color: #1f2937; }
        .societe { font-size: 18px; font-weight: bold; }
        .logo { height: 60px; margin-bottom: 8px; }
        .titre { font-size: 22px; font-weight: bold; }
        .muted { color: #6b7280; font-size: 11px; line-height: 1.6; }
        .box { border: 1px solid #d1d5db; padding: 10px; margin-bottom: 6px; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 18px; }
        table.items th { background: #111827; color: #fff; padding: 7px; font-size: 11px; }
        table.items td { border-bottom: 1px solid #e5e7eb; padding: 7px; }
        /* Figures stay LTR in Latin digits so amounts never reorder. */
        .num { direction: ltr; text-align: left; }
        table.tot { width: 55%; border-collapse: collapse; margin-top: 14px; }
        table.tot td { padding: 6px 8px; border-bottom: 1px solid #e5e7eb; }
        table.tot tr.grand td { background: #111827; color: #fff; font-size: 15px; font-weight: bold; border: 0; }
        .rem { color: #b45309; }
        .ltr { direction: ltr; unicode-bidi: embed; }
    </style>
</head>
<body>

<table width="100%">
    <tr>
        <td width="50%">
            @if($logo)<img src="{{ $logo }}" class="logo" alt="{{ $societe['nom'] }}">@endif
            <div class="societe">{{ $societe['nom'] }}</div>
            <div class="muted">
                {!! nl2br(e($societe['adresse'])) !!}<br>
                @if($societe['telephone']) {{ $t('tel') }}: <span class="ltr">{{ $societe['telephone'] }}</span><br>@endif
                @if($societe['email']) <span class="ltr">{{ $societe['email'] }}</span><br>@endif
                @if($societe['ice']) ICE: <span class="ltr">{{ $societe['ice'] }}</span>@endif
                @if($societe['rc']) — RC: <span class="ltr">{{ $societe['rc'] }}</span>@endif
            </div>
        </td>
        <td width="50%" align="left">
            <div class="titre">{{ $t('titre') }}</div>
            <div class="muted">
                {{ $t('numero') }} <span class="ltr">{{ $invoice->numero }}</span><br>
                <span class="ltr">{{ $invoice->date_facture->format('d/m/Y') }}</span>
                @if($invoice->bc_client)<br>BC: <span class="ltr">{{ $invoice->bc_client }}</span>@endif
            </div>
        </td>
    </tr>
</table>

<div class="box">
    <strong>{{ $t('client') }} :</strong> {{ $invoice->clientNom() }}<br>
    @if($invoice->client)
        <span class="muted">
            {!! nl2br(e($invoice->client->adresse)) !!}
            @if($invoice->client->telephone)<br>{{ $t('tel') }}: <span class="ltr">{{ $invoice->client->telephone }}</span>@endif
            @if($invoice->client->ice)<br>ICE: <span class="ltr">{{ $invoice->client->ice }}</span>@endif
        </span>
    @endif
</div>

<table class="items">
    <thead>
    <tr>
        <th>{{ $t('designation') }}</th>
        <th>{{ $t('qte') }}</th>
        <th>{{ $t('pu') }}</th>
        <th>{{ $t('remise') }}</th>
        <th>{{ $t('total_ht') }}</th>
        <th>{{ $t('tva') }}</th>
        <th>{{ $t('total_ttc') }}</th>
    </tr>
    </thead>
    <tbody>
    @foreach($invoice->items as $item)
        <tr>
            <td>{{ $item->designation }}</td>
            <td class="num">{{ $n($item->quantite, (float) $item->quantite == floor((float) $item->quantite) ? 0 : 2) }}</td>
            <td class="num">{{ $n($item->prix_unitaire) }}</td>
            <td class="num rem">@if((float) $item->remise > 0) −{{ $n($item->remise) }} @else — @endif</td>
            <td class="num">{{ $n($item->total_ht) }}</td>
            <td class="num">{{ (float) $item->tva }}%</td>
            <td class="num">{{ $n($item->total_ttc) }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

<table class="tot">
    <tr>
        <td>{{ $t('sous_total') }}</td>
        <td class="num">{{ $n((float) $invoice->total_ht + $remiseTotale) }} {{ $devise }}</td>
    </tr>
    @if($remiseTotale > 0)
        <tr class="rem">
            <td>{{ $t('remise') }}</td>
            <td class="num">−{{ $n($remiseTotale) }} {{ $devise }}</td>
        </tr>
    @endif
    <tr><td>{{ $t('total_ht') }}</td><td class="num">{{ $n($invoice->total_ht) }} {{ $devise }}</td></tr>
    <tr><td>{{ $t('tva') }}</td><td class="num">{{ $n($invoice->total_tva) }} {{ $devise }}</td></tr>
    <tr class="grand"><td>{{ $t('grand_total') }}</td><td class="num">{{ $n($invoice->total_ttc) }} {{ $devise }}</td></tr>
    <tr><td>{{ $t('paye') }}</td><td class="num">{{ $n($invoice->montant_paye) }} {{ $devise }}</td></tr>
    <tr><td><strong>{{ $t('reste') }}</strong></td><td class="num"><strong>{{ $n((float) $invoice->total_ttc - (float) $invoice->montant_paye) }} {{ $devise }}</strong></td></tr>
</table>

@if($invoice->note)
    <p class="muted">{{ $invoice->note }}</p>
@endif

</body>
</html>
