<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- Les espaces client et administration ne doivent jamais être indexés. --}}
    <meta name="robots" content="noindex, nofollow">

    <title inertia>{{ config('app.name', 'Nonalix IA') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- PWA & Mobile Support -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#005c4b">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <link rel="apple-touch-icon" href="/pwa-icon-192.png">

    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/sw.js')
                    .then(reg => console.log('PWA Service Worker registered:', reg.scope))
                    .catch(err => console.log('PWA Service Worker registration failed:', err));
            });
        }
    </script>

    @inertiaHead
</head>
<body class="h-full bg-slate-50 font-sans text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100">
    @inertia
</body>
</html>
