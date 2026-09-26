@php
    $rtl = \App\Support\Locales::isRtl();
    $align = $rtl ? 'right' : 'left';
    $opposite = $rtl ? 'left' : 'right';
    $t = fn (string $k) => __('app.pdf.'.$k);
    $logo ??= null;
    $n = fn ($v) => number_format((float) $v, 2, ',', ' ');
    $remiseTotale = $invoice->items->sum(fn ($i) => (float) $i->remise);
    // "Paiement par : Espèces" — every mode used on this sale, once each.
    $modes = $invoice->payments->pluck('mode')->unique()->map(fn ($m) => __('app.mode.'.$m))->implode(', ');
    // Company name in two tones like the model: first word dark, the rest blue.
    $nom = trim((string) $societe['nom']);
    [$nom1, $nom2] = array_pad(explode(' ', $nom, 2), 2, '');
@endphp
<!DOCTYPE html>
<html dir="{{ $rtl ? 'rtl' : 'ltr' }}" lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ $t('titre') }} {{ $invoice->numero }}</title>
    <style>
        @page { margin: 0 0 22mm 0; }
        * { font-family: "DejaVu Sans", sans-serif; }
        body { font-size: 11px; color: #1b2a49; margin: 0; direction: {{ $rtl ? 'rtl' : 'ltr' }}; }
        .page { padding: 0 11mm; }
        /* Blue/grey band top and bottom, as on the model. */
        .band { height: 7px; background: #2f6fd8; }
        .band-grey { height: 3px; background: #b7c3d6; }
        .head { width: 100%; margin-top: 12px; }
        .head td { vertical-align: top; }
        .logo { height: 78px; }
        .societe { font-size: 26px; font-weight: bold; letter-spacing: 1px; line-height: 1.1; }
        .societe .b { color: #2f6fd8; }
        .tagline { font-size: 10px; font-weight: bold; color: #1b2a49; margin-top: 4px; line-height: 1.5; }
        .adresse { font-size: 10px; font-weight: bold; line-height: 1.5; border-{{ $align }}: 2px solid #2f6fd8; padding-{{ $align }}: 8px; }
        .muted { color: #5b6b85; font-weight: normal; }
        .rule { border-top: 2px solid #b7c3d6; margin: 12px 0 10px; height: 0; }
        .bandeau { background: #dbe7f8; color: #1b2a49; font-weight: bold; padding: 6px 10px; border-radius: 4px; }
        .titre { font-size: 20px; }
        .numbox { border: 2px solid #1b2a49; border-radius: 6px; padding: 6px 10px; font-weight: bold; font-size: 12px; line-height: 1.6; }
        .client { border: 1px solid #dbe7f8; border-radius: 4px; padding: 6px 10px; line-height: 1.7; font-weight: bold; }
        .client .k { color: #5b6b85; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 12px; border: 1px solid #9fb3cf; }
        table.items th { background: #dbe7f8; color: #1b2a49; padding: 6px 4px; font-size: 9.5px; font-weight: bold; border: 1px solid #9fb3cf; text-align: center; }
        table.items td { padding: 8px 5px; border: 1px solid #9fb3cf; text-align: center; vertical-align: middle; }
        table.items td.des { text-align: {{ $align }}; }
        .des .nom { font-weight: bold; font-size: 11.5px; }
        .des .sub { color: #5b6b85; font-size: 9.5px; }
        /* Amounts stay LTR in Latin digits so figures never reorder. */
        .num { direction: ltr; unicode-bidi: embed; white-space: nowrap; }
        .rem { color: #b45309; }
        table.bottom { width: 100%; margin-top: 10px; border-collapse: separate; border-spacing: 0; }
        table.bottom > tbody > tr > td { vertical-align: top; }
        table.tot { width: 100%; border-collapse: collapse; border: 1px solid #9fb3cf; }
        table.tot td { padding: 5px 8px; border: 1px solid #9fb3cf; font-weight: bold; text-align: {{ $align }}; }
        table.tot td:first-child { background: #dbe7f8; width: 55%; }
        table.tot td.num { text-align: {{ $opposite }}; }
        table.tot tr.grand td { font-size: 13px; }
        table.tot tr.grand td:first-child { background: #b9cff0; }
        .card { border: 1px solid #dbe7f8; border-radius: 6px; padding: 8px 10px; }
        .card .h { font-weight: bold; margin-bottom: 6px; padding-bottom: 4px; border-bottom: 1px solid #dbe7f8; }
        .sig { height: 95px; }
        .sig .lbl { color: #5b6b85; font-size: 9px; text-align: {{ $opposite }}; margin-top: 78px; }
        .cond { margin-top: 14px; font-size: 9.5px; line-height: 1.6; }
        .cond .h { font-weight: bold; text-decoration: underline; }
        .note { font-size: 9.5px; color: #5b6b85; margin-top: 8px; }
        .foot { position: fixed; bottom: 0; left: 0; right: 0; }
        .foot .txt { text-align: center; font-size: 10px; color: #1b2a49; padding: 6px 0; background: #f2f6fc; }
        .foot .txt b { letter-spacing: 1px; }
    </style>
</head>
<body>

<div class="band"></div>
<div class="band-grey"></div>

<div class="page">

<table class="head">
    <tr>
        <td width="22%" align="{{ $align }}">
            @if($logo)<img src="{{ $logo }}" class="logo" alt="{{ $societe['nom'] }}">@endif
        </td>
        <td width="46%" align="center">
            <div class="societe">{{ $nom1 }} <span class="b">{{ $nom2 }}</span></div>
            <div class="tagline">{{ $t('activite') }}</div>
            <div class="tagline muted">
                @if($societe['telephone']){{ $t('tel') }} : <span class="num">{{ $societe['telephone'] }}</span>@endif
                @if($societe['email']) · <span class="num">{{ $societe['email'] }}</span>@endif
            </div>
        </td>
        <td width="32%">
            <div class="adresse">
                {!! nl2br(e($societe['adresse'])) !!}
                <div class="muted">
                    @if($societe['ice'])ICE : <span class="num">{{ $societe['ice'] }}</span><br>@endif
                    @if($societe['rc'])RC : <span class="num">{{ $societe['rc'] }}</span>@endif
                </div>
            </div>
        </td>
    </tr>
</table>

<div class="rule"></div>

<table width="100%">
    <tr>
        <td width="68%" valign="top">
            <div class="bandeau titre">{{ $t('titre') }}</div>
        </td>
        <td width="4%"></td>
        <td width="28%" valign="top">
            <div class="numbox">
                {{ $t('numero') }} <span class="num">{{ $invoice->numero }}</span><br>
                <span style="font-weight:normal">{{ $t('date') }} :</span> <span class="num">{{ $invoice->date_facture->format('d / m / Y') }}</span>
                @if($invoice->bc_client)<br><span style="font-weight:normal">BC :</span> <span class="num">{{ $invoice->bc_client }}</span>@endif
            </div>
        </td>
    </tr>
</table>

<table width="100%" style="margin-top:10px">
    <tr>
        <td width="68%">
            <div class="bandeau">{{ $t('infos_client') }}</div>
            <div class="client">
                <span class="k">{{ $t('nom_societe') }} :</span> {{ $invoice->clientNom() }}<br>
                @if($invoice->client)
                    @if($invoice->client->rc)<span class="k">RC :</span> <span class="num">{{ $invoice->client->rc }}</span><br>@endif
                    @if($invoice->client->ice)<span class="k">ICE :</span> <span class="num">{{ $invoice->client->ice }}</span><br>@endif
                    @if($invoice->client->telephone)<span class="k">{{ $t('tel') }} :</span> <span class="num">{{ $invoice->client->telephone }}</span><br>@endif
                    @if($invoice->client->adresse)<span class="k">{{ $t('adresse') }} :</span> <span class="muted">{{ $invoice->client->adresse }}</span>@endif
                @endif
            </div>
        </td>
        <td></td>
    </tr>
</table>

<table class="items">
    <thead>
    <tr>
        <th width="5%">N°</th>
        <th>{{ $t('designation') }}</th>
        <th width="9%">{{ $t('qte_long') }}</th>
        <th width="12%">{{ $t('pu') }}<br>(HT)</th>
        <th width="12%">{{ $t('prix_total') }}<br>(HT)</th>
        <th width="9%">{{ $t('tva') }}</th>
        <th width="13%">{{ $t('prix_total') }}<br>(TTC)</th>
    </tr>
    </thead>
    <tbody>
    @foreach($invoice->items as $item)
        <tr>
            <td class="num">{{ $loop->iteration }}</td>
            <td class="des">
                <div class="nom">{{ $item->designation }}</div>
                @if($item->article?->reference)<div class="sub num">{{ $item->article->reference }}</div>@endif
                @if((float) $item->remise > 0)<div class="sub rem">{{ $t('remise') }} : <span class="num">−{{ $n($item->remise) }}</span></div>@endif
            </td>
            <td class="num">{{ number_format((float) $item->quantite, (float) $item->quantite == floor((float) $item->quantite) ? 0 : 2, ',', ' ') }}</td>
            <td class="num">{{ $n($item->prix_unitaire) }}</td>
            <td class="num">{{ $n($item->total_ht) }}</td>
            <td class="num">{{ (float) $item->tva }}%</td>
            <td class="num">{{ $n($item->total_ttc) }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

<table class="bottom">
    <tr>
        <td width="52%">
            <div class="card" style="margin-top:36px">
                <div class="h">{{ $t('mode_reglement') }}</div>
                {{ $t('paiement_par') }} : <strong>{{ $modes !== '' ? $modes : '—' }}</strong>
            </div>
            <div class="cond">
                <div class="h">{{ $t('conditions') }} :</div>
                • {{ $t('cond_1') }}<br>
                • {{ $t('cond_2') }}<br>
                • {{ $t('cond_3') }}
            </div>
            @if($invoice->note)<div class="note">{{ $invoice->note }}</div>@endif
        </td>
        <td width="4%"></td>
        <td width="44%">
            <table class="tot">
                @if($remiseTotale > 0)
                    <tr><td>{{ $t('sous_total') }}</td><td class="num">{{ $n((float) $invoice->total_ht + $remiseTotale) }}</td></tr>
                    <tr class="rem"><td>{{ $t('remise') }}</td><td class="num">−{{ $n($remiseTotale) }}</td></tr>
                @endif
                <tr><td>{{ $t('total_ht') }}</td><td class="num">{{ $n($invoice->total_ht) }}</td></tr>
                <tr><td>{{ $t('tva') }}</td><td class="num">{{ $n($invoice->total_tva) }}</td></tr>
                <tr class="grand"><td>{{ $t('total_ttc') }}</td><td class="num">{{ $n($invoice->total_ttc) }} {{ $devise }}</td></tr>
                <tr><td>{{ $t('paye') }}</td><td class="num">{{ $n($invoice->montant_paye) }}</td></tr>
                <tr><td>{{ $t('reste') }}</td><td class="num">{{ $n((float) $invoice->total_ttc - (float) $invoice->montant_paye) }}</td></tr>
            </table>
            <div class="card sig" style="margin-top:14px">
                <div class="h">{{ $t('cachet') }}</div>
                <div class="lbl">{{ $t('signature_client') }}</div>
            </div>
        </td>
    </tr>
</table>

</div>

<div class="foot">
    <div class="txt"><b>{{ $societe['nom'] }}</b> &nbsp;—&nbsp; {{ $t('slogan') }} &nbsp;·&nbsp; <span class="num">{{ $invoice->numero }}</span></div>
    <div class="band-grey"></div>
    <div class="band"></div>
</div>

</body>
</html>
