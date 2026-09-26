@php($rtl = \App\Support\Locales::isRtl())
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ \App\Models\Setting::get('societe_nom', config('app.name')) }} — {{ __('app.pos.label') }}</title>
    <link rel="icon" href="{{ asset('assets/chinwi-the-doctor.jpeg') }}">
    @vite('resources/react/main.tsx')
</head>
<body class="h-full">
    <div id="root"></div>
</body>
</html>
