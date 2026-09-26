@php($rtl = \App\Support\Locales::isRtl())
<!DOCTYPE html>
<html lang="{{ app()->getLocale() === 'ary' ? 'ar-MA' : app()->getLocale() }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ \App\Models\Setting::get('societe_nom', config('app.name')) }} — {{ __('app.pos.label') }}</title>
    <link rel="icon" href="{{ asset('assets/chinwi-the-doctor.jpeg') }}">
    @viteReactRefresh
    @vite('resources/react/main.tsx')
</head>
<body class="h-full">
    <div id="root"></div>
</body>
</html>
