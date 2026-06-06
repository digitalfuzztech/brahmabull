<div
    id="preloader"
    class="fixed inset-0 z-[99999] flex items-center justify-center bg-slate-950">

    <div class="relative w-40 h-40 flex items-center justify-center">

        <!-- OUTER SPIN -->
        <div class="absolute inset-0 rounded-full border-4 border-transparent border-t-purple-500 animate-spin"></div>

        <!-- INNER SPIN -->
        <div class="absolute inset-6 rounded-full border-4 border-transparent border-b-indigo-500 animate-spin-slow"></div>

        <!-- LOGO -->
        <img
            src="{{ asset('images/logo-brahma.png') }}"
            class="w-20 h-20 z-10 object-contain"
            alt="Loading"
        >

    </div>

</div>
