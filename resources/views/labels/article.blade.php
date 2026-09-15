<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ in_array(app()->getLocale(), ['ar', 'ary']) ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $article->designation }} — {{ $article->code_barre }}</title>
    <style>
        * { box-sizing: border-box; }
        html, body { margin: 0; background: #fff; color: #000; font-family: Arial, Helvetica, sans-serif; }
        body { min-height: 100vh; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 24px; }
        .label { text-align: center; }
        .societe { font-size: 14px; color: #444; margin-bottom: 8px; }
        .nom { font-size: 26px; font-weight: 700; margin-bottom: 4px; }
        .ref { font-size: 13px; color: #666; font-family: monospace; margin-bottom: 18px; }
        .prix { font-size: 34px; font-weight: 800; margin-top: 14px; }
        /* Never let the browser resample the bars: 1 image pixel = 1 screen pixel. */
        img { display: block; margin: 0 auto; image-rendering: pixelated; image-rendering: crisp-edges; max-width: 100%; }
        .actions { margin-top: 32px; display: flex; gap: 12px; }
        button, a.btn { font: inherit; font-size: 16px; font-weight: 600; padding: 12px 22px; border-radius: 8px; border: 1px solid #999; background: #f4f4f4; color: #000; cursor: pointer; text-decoration: none; }
        button.primary { background: #059669; border-color: #059669; color: #fff; }
        .aide { margin-top: 18px; font-size: 13px; color: #666; max-width: 32rem; text-align: center; }
        @media print {
            body { padding: 0; min-height: auto; }
            .actions, .aide { display: none; }
            @page { margin: 6mm; }
        }
    </style>
</head>
<body>
    <div class="label">
        @if ($societe)
            <div class="societe">{{ $societe }}</div>
        @endif
        <div class="nom">{{ $article->designation }}</div>
        <div class="ref">{{ $article->reference }}</div>
        <img src="{{ $barcode }}" alt="{{ $article->code_barre }}">
        <div class="prix">{{ \App\Support\Money::format($article->prix_vente) }}</div>
    </div>

    <div class="actions">
        <button type="button" class="primary" onclick="window.print()">{{ __('app.invoice.print') }}</button>
        <a class="btn" href="{{ route('article.barcode', $article) }}">PNG</a>
    </div>
    <p class="aide">{{ __('app.article.etiquette_aide') }}</p>
</body>
</html>
