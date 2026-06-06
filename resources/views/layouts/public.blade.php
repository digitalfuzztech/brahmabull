<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description"
          content="BrahmaBull Member Platform">

    <meta name="robots"
          content="index,follow">

    <meta property="og:title"
          content="BrahmaBull">

    <meta property="og:description"
          content="BrahmaBull Member Platform">
    <title>{{ $title ?? 'BrahmaBull Member Portal' }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;500;700;800;900&display=swap" rel="stylesheet">

    <link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        [x-cloak]{
            display:none !important;
        }
    </style>
    @livewireStyles
</head>

<body class="bg-slate-950 text-white pt-20">


    <x-public-preloader />
    <x-public-header />

    <main class="min-h-screen">
        {{ $slot }}
    </main>

    <x-footer />



@livewireScripts
</body>
<!--
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
-->
</html>
