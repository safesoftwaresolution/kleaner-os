<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ config('app.name', 'Kleaner OS') }}</title>
        <meta name="description" content="Kleaner OS es el sistema operativo de tu lavadero: registro por placa, tablero en vivo, aviso por WhatsApp y reportes del día.">

        <meta name="theme-color" content="#F7FBFD" media="(prefers-color-scheme: light)">
        <meta name="theme-color" content="#0A1626" media="(prefers-color-scheme: dark)">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="icon" href="/favicon.ico" sizes="48x48">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">
        <link rel="manifest" href="/manifest.webmanifest">

        @routes
        @vite(['resources/js/app.ts'])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
