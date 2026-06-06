<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>{{ $title ?? 'Backend | BrahmaBull Gaming Club' }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;500;700;800;900&display=swap" rel="stylesheet">

    <link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @livewireStyles
</head>

<body class="bg-slate-950 text-white">

<div class="flex min-h-screen">

    {{-- Sidebar --}}
    <x-private-sidebar />

    {{-- Main Area --}}
    <div class="flex flex-1 flex-col min-h-screen">

        {{-- Header --}}
        <x-private-header />

        {{-- Content --}}
        <main class="flex-1 p-8">

            {{ $slot }}

        </main>

        {{-- Footer --}}
        <x-private-footer />

    </div>

</div>

@livewireScripts

</body>
</html>
