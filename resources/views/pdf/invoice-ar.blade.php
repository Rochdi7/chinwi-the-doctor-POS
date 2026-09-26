@php
    $t = fn (string $k) => __('app.pdf.'.$k);
    $logo ??= null;
    $n = fn ($v, int $d = 2) => number_format((float) $v, $d, ',', ' ');
    $remiseTotale = $invoice->items->sum(fn ($i) => (float) $i->remise);
    $modes = $invoice->payments->pluck('mode')->unique()->map(fn ($m) => __('app.mode.'.$m))->implode('، ');
    $nom = trim((string) $societe['nom']);
    [$nom1, $nom2] = array_pad(explode(' ', $nom, 2), 2, '');
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>{{ $t('titre') }} {{ $invoice->numero }}</title>
    <style>
        @page { margin: 14mm 11mm 24mm 11mm; margin-header: 0; margin-footer: 6mm; header: h; footer: f; }
        body { font-family: tajawal; font-size: 11px; color: #1b2a49; }
        table.box { width: 100%; border-collapse: collapse; }
        td.bandeau { background: #dbe7f8; font-weight: bold; padding: 6px 10px; }
        td.numbox { border: 2px solid #1b2a49; padding: 6px 10px; font-weight: bold; font-size: 12px; line-height: 1.6; }
        td.client { border: 1px solid #b9cff0; padding: 6px 10px; line-height: 1.7; font-weight: bold; }
        td.card { border: 1px solid #b9cff0; padding: 8px 10px; }
        td.cardh { border: 1px solid #b9cff0; border-bottom: 0; padding: 6px 10px 2px; font-weight: bold; }
        .band { height: 7px; background: #2f6fd8; }
        .band-grey { height: 3px; background: #b7c3d6; }
        .logo { height: 78px; }
        .societe { font-size: 26px; font-weight: bold; line-height: 1.1; }
        .societe .b { color: #2f6fd8; }
        .tagline { font-size: 10px; font-weight: bold; margin-top: 4px; line-height: 1.5; }
        .muted { color: #5b6b85; font-weight: normal; }
        .adresse { font-size: 10px; font-weight: bold; line-height: 1.5; border-right: 2px solid #2f6fd8; padding-right: 8px; }
        .rule { border-top: 2px solid #b7c3d6; margin: 12px 0 10px; }
        .bandeau { background: #dbe7f8; font-weight: bold; padding: 6px 10px; }
        .titre { font-size: 20px; }
        .numbox { border: 2px solid #1b2a49; padding: 6px 10px; font-weight: bold; font-size: 12px; line-height: 1.6; }
        .client { border: 1px solid #dbe7f8; padding: 6px 10px; line-height: 1.7; font-weight: bold; }
        .client .k { color: #5b6b85; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 12px; }
        table.items th { background: #dbe7f8; padding: 6px 4px; font-size: 9.5px; border: 1px solid #9fb3cf; text-align: center; }
        table.items td { padding: 8px 5px; border: 1px solid #9fb3cf; text-align: center; vertical-align: middle; }
        table.items td.des { text-align: right; }
        .des .nom { font-weight: bold; font-size: 11.5px; }
        .des .sub { color: #5b6b85; font-size: 9.5px; }
        /* Figures stay LTR in Latin digits so amounts never reorder. */
        .num, .ltr { direction: ltr; unicode-bidi: embed; }
        .rem { color: #b45309; }
        table.tot { width: 100%; border-collapse: collapse; }
        table.tot td { padding: 5px 8px; border: 1px solid #9fb3cf; font-weight: bold; }
        table.tot td.k { background: #dbe7f8; width: 55%; }
        table.tot td.num { text-align: left; }
        table.tot tr.grand td { font-size: 13px; }
        table.tot tr.grand td.k { background: #b9cff0; }
        .card { border: 1px solid #dbe7f8; padding: 8px 10px; }
        .card .h { font-weight: bold; margin-bottom: 6px; padding-bottom: 4px; border-bottom: 1px solid #dbe7f8; }
        .sig { height: 95px; }
        .sig .lbl { color: #5b6b85; font-size: 9px; text-align: left; margin-top: 70px; }
        .cond { margin-top: 14px; font-size: 9.5px; line-height: 1.6; }
        .cond .h { font-weight: bold; text-decoration: underline; }
        .note { font-size: 9.5px; color: #5b6b85; margin-top: 8px; }
        .foot { text-align: center; font-size: 10px; padding: 6px 0; background: #f2f6fc; }
    </style>
</head>
<body>

<htmlpageheader name="h">
    <table class="box"><tr><td style="background:#2f6fd8;height:7px"></td></tr><tr><td style="background:#b7c3d6;height:3px"></td></tr></table>
</htmlpageheader>
<htmlpagefooter name="f">
    <table class="box">
        <tr><td style="background:#f2f6fc;text-align:center;font-size:10px;padding:6px 0"><b>{{ $societe['nom'] }}</b> &nbsp;—&nbsp; {{ $t('slogan') }} &nbsp;·&nbsp; <span class="ltr">{{ $invoice->numero }}</span></td></tr>
        <tr><td style="background:#b7c3d6;height:3px"></td></tr>
        <tr><td style="background:#2f6fd8;height:7px"></td></tr>
    </table>
</htmlpagefooter>

<table width="100%">
    <tr>
        <td width="22%" align="right" valign="top">
            @if($logo)<img src="{{ $logo }}" class="logo" alt="{{ $societe['nom'] }}">@endif
        </td>
        <td width="46%" align="center" valign="top">
            <div class="societe"><span class="ltr">{{ $nom1 }} <span class="b">{{ $nom2 }}</span></span></div>
            <div class="tagline">{{ $t('activite') }}</div>
            <div class="tagline muted">
                @if($societe['telephone']){{ $t('tel') }} : <span class="ltr">{{ $societe['telephone'] }}</span>@endif
                @if($societe['email']) · <span class="ltr">{{ $societe['email'] }}</span>@endif
            </div>
        </td>
        <td width="32%" valign="top">
            <div class="adresse">
                {!! nl2br(e($societe['adresse'])) !!}
                <div class="muted">
                    @if($societe['ice'])ICE : <span class="ltr">{{ $societe['ice'] }}</span><br>@endif
                    @if($societe['rc'])RC : <span class="ltr">{{ $societe['rc'] }}</span>@endif
                </div>
            </div>
        </td>
    </tr>
</table>

<div class="rule"></div>

<table width="100%">
    <tr>
        <td width="68%" valign="top"><table class="box"><tr><td class="bandeau titre">{{ $t('titre') }}</td></tr></table></td>
        <td width="4%"></td>
        <td width="28%" valign="top">
            <table class="box"><tr><td class="numbox">
                {{ $t('numero') }} <span class="ltr">{{ $invoice->numero }}</span><br>
                <span style="font-weight:normal">{{ $t('date') }} :</span> <span class="ltr">{{ $invoice->date_facture->format('d / m / Y') }}</span>
                @if($invoice->bc_client)<br><span style="font-weight:normal">{{ $t('bc') }} :</span> <span class="ltr">{{ $invoice->bc_client }}</span>@endif
            </td></tr></table>
        </td>
    </tr>
</table>

<table width="100%" style="margin-top:10px">
    <tr>
        <td width="68%">
            <table class="box"><tr><td class="bandeau">{{ $t('infos_client') }}</td></tr><tr><td class="client">
                <span class="k">{{ $t('nom_societe') }} :</span> {{ $invoice->clientNom() }}<br>
                @if($invoice->client)
                    @if($invoice->client->rc)<span class="k">RC :</span> <span class="ltr">{{ $invoice->client->rc }}</span><br>@endif
                    @if($invoice->client->ice)<span class="k">ICE :</span> <span class="ltr">{{ $invoice->client->ice }}</span><br>@endif
                    @if($invoice->client->telephone)<span class="k">{{ $t('tel') }} :</span> <span class="ltr">{{ $invoice->client->telephone }}</span><br>@endif
                    @if($invoice->client->adresse)<span class="k">{{ $t('adresse') }} :</span> <span class="muted">{{ $invoice->client->adresse }}</span>@endif
                @endif
            </td></tr></table>
        </td>
        <td></td>
    </tr>
</table>

<table class="items">
    <thead>
    <tr>
        <th width="5%">{{ $t('num_col') }}</th>
        <th>{{ $t('designation') }}</th>
        <th width="9%">{{ $t('qte_long') }}</th>
        <th width="12%">{{ $t('pu') }}<br>{{ $t('ht') }}</th>
        <th width="12%">{{ $t('prix_total') }}<br>{{ $t('ht') }}</th>
        <th width="9%">{{ $t('tva') }}</th>
        <th width="13%">{{ $t('prix_total') }}<br>{{ $t('ttc') }}</th>
    </tr>
    </thead>
    <tbody>
    @foreach($invoice->items as $item)
        <tr>
            <td class="num">{{ $loop->iteration }}</td>
            <td class="des">
                <b style="font-size:11.5px">{{ $item->designation }}</b>
                @if($item->article?->reference)<br><span style="color:#5b6b85;font-size:9.5px">{{ $item->article->reference }}</span>@endif
                @if((float) $item->remise > 0)<br><span class="rem" style="font-size:9.5px">{{ $t('remise') }} : <span class="ltr">−{{ $n($item->remise) }}</span></span>@endif
            </td>
            <td class="num">{{ $n($item->quantite, (float) $item->quantite == floor((float) $item->quantite) ? 0 : 2) }}</td>
            <td class="num">{{ $n($item->prix_unitaire) }}</td>
            <td class="num">{{ $n($item->total_ht) }}</td>
            <td class="num">{{ (float) $item->tva }}%</td>
            <td class="num">{{ $n($item->total_ttc) }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

<table width="100%" style="margin-top:10px">
    <tr>
        <td width="52%" valign="top">
            <br><br>
            <table class="box"><tr><td class="cardh">{{ $t('mode_reglement') }}</td></tr><tr><td class="card" style="border-top:0">
                {{ $t('paiement_par') }} : <strong>{{ $modes !== '' ? $modes : '—' }}</strong>
            </td></tr></table>
            <div class="cond">
                <div class="h">{{ $t('conditions') }} :</div>
                • {{ $t('cond_1') }}<br>
                • {{ $t('cond_2') }}<br>
                • {{ $t('cond_3') }}
            </div>
            @if($invoice->note)<div class="note">{{ $invoice->note }}</div>@endif
        </td>
        <td width="4%"></td>
        <td width="44%" valign="top">
            <table class="tot">
                @if($remiseTotale > 0)
                    <tr><td class="k">{{ $t('sous_total') }}</td><td class="num">{{ $n((float) $invoice->total_ht + $remiseTotale) }}</td></tr>
                    <tr class="rem"><td class="k">{{ $t('remise') }}</td><td class="num">−{{ $n($remiseTotale) }}</td></tr>
                @endif
                <tr><td class="k">{{ $t('total_ht') }}</td><td class="num">{{ $n($invoice->total_ht) }}</td></tr>
                <tr><td class="k">{{ $t('tva') }}</td><td class="num">{{ $n($invoice->total_tva) }}</td></tr>
                <tr class="grand"><td class="k">{{ $t('total_ttc') }}</td><td class="num">{{ $n($invoice->total_ttc) }} {{ $devise }}</td></tr>
                <tr><td class="k">{{ $t('paye') }}</td><td class="num">{{ $n($invoice->montant_paye) }}</td></tr>
                <tr><td class="k">{{ $t('reste') }}</td><td class="num">{{ $n((float) $invoice->total_ttc - (float) $invoice->montant_paye) }}</td></tr>
            </table>
            <br>
            <table class="box"><tr><td class="cardh">{{ $t('cachet') }}</td></tr><tr><td class="card" style="border-top:0;height:80px;vertical-align:bottom;text-align:left;color:#5b6b85;font-size:9px">{{ $t('signature_client') }}</td></tr></table>
        </td>
    </tr>
</table>

</body>
</html>
