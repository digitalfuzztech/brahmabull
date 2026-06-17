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
        <main id="mainContent" class="flex-1 p-8 pt-28 transition-all duration-300 lg:ml-64">

            {{ $slot }}

        </main>

        {{-- Footer --}}
        <x-private-footer />

    </div>

</div>

@livewireScripts
<script src="https://unpkg.com/lucide@latest"></script>
<script>
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        const content = document.getElementById('mainContent');
        const header = document.getElementById('headerBar');
        const btn = document.getElementById('sidebarToggleBtn');

        // MOBILE
        if (window.innerWidth < 1024) {
            sidebar.classList.remove('-translate-x-full');
            overlay.classList.remove('hidden');
            return;
        }

        // DESKTOP COLLAPSE
        sidebar.classList.toggle('collapsed');

        if (sidebar.classList.contains('collapsed')) {

            content.classList.remove('lg:ml-64');
            content.classList.add('lg:ml-20');

            header.classList.remove('lg:left-64');
            header.classList.add('lg:left-20');

            btn.classList.remove('lg:left-64');
            btn.classList.add('lg:left-20');

        } else {

            content.classList.add('lg:ml-64');
            content.classList.remove('lg:ml-20');

            header.classList.add('lg:left-64');
            header.classList.remove('lg:left-20');

            btn.classList.add('lg:left-64');
            btn.classList.remove('lg:left-20');
        }

        const brand = document.getElementById('sidebarBrand');

        if (sidebar.classList.contains('collapsed')) {
            brand.classList.add('hidden');
        } else {
            brand.classList.remove('hidden');
        }
    }

    function closeSidebarMobile() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');

        sidebar.classList.add('-translate-x-full');
        overlay.classList.add('hidden');
    }
</script>
<script>
    function initLucide() {
        if (window.lucide) {
            lucide.createIcons();
        }
    }

    document.addEventListener("DOMContentLoaded", initLucide);
    document.addEventListener("livewire:navigated", initLucide);
    document.addEventListener("livewire:updated", initLucide);
</script>
</body>
</html>
