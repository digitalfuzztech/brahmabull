@props(['title' => 'Game', 'desc' => 'Win real rewards', 'image' => '/images/games/default.jpg' ])

<div
    class="group relative cursor-pointer rounded-2xl border border-slate-800 bg-slate-900 p-4 transition
           hover:-translate-y-2 hover:border-purple-500 hover:shadow-[0_0_30px_rgba(168,85,247,0.3)]">

    <!-- Glow overlay -->
    <div
        class="absolute inset-0 rounded-2xl bg-gradient-to-br from-purple-600/10 to-indigo-700/10 opacity-0 group-hover:opacity-100 transition">
    </div>

    <!-- Game image -->
    <div class="relative mb-4 aspect-square rounded-xl overflow-hidden">

        <img
            src="{{ $image }}"
            class="h-full w-full object-cover group-hover:scale-110 transition duration-500"
            alt="{{ $title }}"
        />

    </div>

    <!-- Content -->
    <div class="relative">

        <h3 class="font-bold text-white group-hover:text-purple-300 transition">
            {{ $title }}
        </h3>

        <p class="text-sm text-slate-400">
            {{ $desc }}
        </p>

    </div>

</div>
