<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title inertia>{{ config('app.name', 'Gaeld') }}</title>

        {{-- Favicon & icons --}}
        <link rel="icon" type="image/x-icon" href="/favicon.ico">
        <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
        <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
        <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
        <link rel="manifest" href="/site.webmanifest">
        <meta name="msapplication-config" content="/browserconfig.xml">
        <meta name="theme-color" content="#33cc66">

        {{-- Prevent flash of wrong theme --}}
        <script>
          (function(){
            var t=localStorage.getItem('gaeld-theme')||'system';
            var d=t==='dark'||(t==='system'&&window.matchMedia('(prefers-color-scheme:dark)').matches);
            if(d)document.documentElement.classList.add('dark');
          })();
        </script>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @vite('resources/js/cookieConsent.js')
        @include('partials.google-analytics')
        @inertiaHead
    </head>
    <body>
        @if(config('services.google.gtm_id'))
        <noscript><iframe src="https://www.googletagmanager.com/ns.html?id={{ config('services.google.gtm_id') }}"
        height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
        @endif
        @inertia
    </body>
</html>
