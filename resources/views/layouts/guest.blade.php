<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>

    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>BrahmaBull Gaming Club</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

</head>

<body class="min-h-screen bg-slate-950 text-white antialiased overflow-x-hidden">

<!-- BACKGROUND GLOWS -->

<div class="fixed inset-0 overflow-hidden pointer-events-none">

    <div
        class="absolute -left-40 top-0 h-96 w-96 rounded-full bg-purple-700/20 blur-3xl">
    </div>

    <div
        class="absolute right-0 top-20 h-96 w-96 rounded-full bg-indigo-700/20 blur-3xl">
    </div>

</div>

<!-- PAGE -->

<div class="relative flex min-h-screen items-center justify-center px-6 py-12">

    <div class="w-full max-w-md">

        <!-- LOGO -->

        <div class="mb-8 text-center">

            <a href="/">

                <img
                    src="{{ asset('images/logo-brahma.png') }}"
                    class="mx-auto w-20"
                    alt="BrahmaBull"
                >

            </a>

            <h1 class="mt-4 text-3xl font-black uppercase tracking-wider">

                BrahmaBull

            </h1>

            <p class="text-sm uppercase tracking-[0.3em] text-purple-400">

                Gaming Club

            </p>

        </div>

        <!-- CARD -->

        <div
            class="rounded-3xl border border-slate-800 bg-slate-900/80 p-8 backdrop-blur-xl shadow-2xl"
        >

            {{ $slot }}

        </div>

    </div>

</div>

</body>

</html>
