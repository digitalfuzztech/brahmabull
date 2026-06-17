@php
    use Illuminate\Support\Facades\Storage;
        $isGuest = !auth()->check();

        $isPlayer = auth()->check() && auth()->user()->hasRole('player');

        $isAgent = auth()->check() && auth()->user()->hasRole('agent');

        $isAdmin = auth()->check() && auth()->user()->hasRole('admin');

@endphp
<div x-data="{ mobileMenu:false }">
    <header class="fixed top-0 left-0 right-0 z-[999] w-full border-b border-slate-800/50 bg-slate-950/80 backdrop-blur-xl">

        <div class="mx-auto max-w-7xl px-6">

            <div class="flex h-20 items-center justify-between">

                <!-- Logo -->
                <div class="flex items-center gap-1 md:gap-3">

                    <a
                        href="/">

                        <img src="{{asset('images/logo.png')}}"
                             class="w-10 md:w-20 rounded-xl object-cover"
                             alt="logo">

                    </a>

                    <a href="/">

                        <h1
                            class="text-sm md:text-xl font-black uppercase tracking-wider text-white">

                            BrahmaBull

                        </h1>

                        <p class="text-xs uppercase tracking-widest text-purple-400">

                           GAMING

                        </p>

                    </a>

                </div>

                @if($isGuest)

                    <div class="flex items-center gap-4">

                        <a
                            href="{{ route('login') }}"
                            class="rounded-xl border px-3 py-1 md:px-5 md:py-2 text-sm font-semibold transition hover:border-purple-500 hover:bg-purple-500">

                            Login

                        </a>

                        <a
                            href="{{ route('register') }}"
                            class="rounded-xl bg-gradient-to-r from-purple-600 to-indigo-600 px-3 py-1 md:px-5 md:py-2 text-sm font-bold transition hover:scale-105">

                            Register

                        </a>

                    </div>

                @endif


                @if($isPlayer)
                    <div class="flex items-center gap-4">
                        <!-- MOBILE HAMBURGER -->
                        <button
                            @click="mobileMenu = true"
                            class="md:hidden flex items-center justify-center h-10 w-10 rounded-xl border border-slate-700"
                        >
                            <svg
                                class="h-6 w-6"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M4 6h16M4 12h16M4 18h16"
                                />
                            </svg>
                        </button>
                        <livewire:pages.notification-bell />
                        <div class="hidden md:flex items-center gap-6">

                            <a href="{{ route('games') }}" class="font-bold hover:text-purple-400 transition-all">Play Now</a>


                            <a href="{{route('cashouts')}}" class="font-bold hover:text-purple-400 transition-all">Request Withdrawal</a>
                            <div
                                x-data="{ open: false }"
                                class="relative"
                            >
                                <button
                                    @click="open = !open"
                                    class="flex items-center gap-3 rounded-3xl p-1 border border-purple-400"
                                >
                                    <img
                                        src="{{ auth()->user()->photo
            ? Storage::url(auth()->user()->photo)
            : asset('images/default-user.png') }}"
                                        class="h-10 w-10 rounded-full object-cover border-2 border-purple-500"
                                    >

                                    <span class="font-semibold">
        {{ auth()->user()->name }}
    </span>

                                    <svg
                                        class="h-4 w-4"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M19 9l-7 7-7-7"
                                        />
                                    </svg>
                                </button>

                                <div
                                    x-show="open"
                                    x-cloak
                                    @click.outside="open = false"
                                    x-transition
                                    class="absolute right-0 mt-3 w-56 overflow-hidden rounded-2xl border border-slate-700 bg-slate-900 shadow-2xl"
                                >
                                    <a
                                        href="/profile"
                                        class="block px-5 py-3 hover:bg-slate-800"
                                    >
                                        My Profile
                                    </a>


                                    <button
                                        x-data
                                        @click="$dispatch('open-logout-modal')"
                                        class="w-full px-5 py-3 text-left text-red-400 hover:bg-slate-800"
                                    >
                                        Logout
                                    </button>
                                </div>
                            </div>


                        </div>
                    </div>


                @endif


                @if($isAgent)

                    <div class="flex items-center gap-4">

                        <a
                            href="/agent"
                            class="rounded-xl bg-gradient-to-r from-purple-600 to-indigo-600 px-5 py-2">

                            Dashboard

                        </a>
                        <button
                            x-data
                            @click="$dispatch('open-logout-modal')"
                            class="rounded-xl border border-red-500 px-4 py-2 text-sm font-semibold text-red-400 hover:bg-red-500 hover:text-white transition"
                        >
                            Logout
                        </button>
                    </div>

                @endif


                @if($isAdmin)

                    <div class="flex items-center gap-4">

                        <a
                            href="/admin"
                            class="rounded-xl bg-gradient-to-r from-purple-600 to-indigo-600 px-5 py-2">

                            Dashboard

                        </a>
                        <button
                            x-data
                            @click="$dispatch('open-logout-modal')"
                            class="rounded-xl border border-red-500 px-4 py-2 text-sm font-semibold text-red-400 hover:bg-red-500 hover:text-white transition"
                        >
                            Logout
                        </button>
                    </div>


                @endif

            </div>

        </div>

    </header>

    <!-- MOBILE SIDEBAR -->
    @if($isPlayer)
        <div
            x-show="mobileMenu"
            x-cloak
            x-transition.opacity
            class="fixed inset-0 z-[9998] md:hidden"
        >
        <!-- Backdrop -->
        <div
            class="absolute inset-0 bg-black/70 backdrop-blur-sm"
            @click="mobileMenu = false"
        ></div>

        <!-- Sidebar -->
        <div
            x-show="mobileMenu"
            x-cloak
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="-translate-x-full"
            x-transition:enter-end="translate-x-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="translate-x-0"
            x-transition:leave-end="-translate-x-full"
            class="absolute left-0 top-0 h-full w-80 bg-slate-950 border-r border-slate-800 shadow-2xl"
        >

            <!-- Header -->
            <div class="flex items-center justify-between border-b border-slate-800 p-5">

                <div class="flex items-center gap-3">

                    <img
                        src="{{ auth()->user()->photo
                        ? Storage::url(auth()->user()->photo)
                        : asset('images/default-user.png') }}"
                        class="h-12 w-12 rounded-full border-2 border-purple-500 object-cover"
                    >

                    <div>

                        <div class="font-bold text-white">
                            {{ auth()->user()->name }}
                        </div>

                        <div class="text-xs text-purple-400">
                            Player
                        </div>

                    </div>

                </div>

                <button
                    @click="mobileMenu = false"
                    class="text-slate-400 hover:text-white"
                >
                    ✕
                </button>

            </div>

            <!-- Links -->
            <div class="p-4 space-y-2">

                <a href="{{ route('games') }}" class="block rounded-xl px-4 py-3 hover:bg-slate-800">Play Now</a>


                <a href="{{route('cashouts')}}"  class="block rounded-xl px-4 py-3 hover:bg-slate-800">Request Withdrawal</a>

                <a
                    href="{{route('profile')}}"
                    class="block rounded-xl px-4 py-3 hover:bg-slate-800"
                >
                    My Profile
                </a>

                <a
                    href="{{route('player.notifications')}}"
                    class="block rounded-xl px-4 py-3 hover:bg-slate-800"
                >
                    Notifications
                </a>

                <button
                    @click="
                    mobileMenu=false;
                    $dispatch('open-logout-modal');
                "
                    class="w-full rounded-xl px-4 py-3 text-left text-red-400 hover:bg-slate-800"
                >
                    Logout
                </button>

            </div>

        </div>

    </div>
@endif
    <form
        id="logout-form"
        method="POST"
        action="{{ route('logout') }}"
        class="hidden"
    >
        @csrf
    </form>

    <div
        x-data="{ open: false, loading:false }"
        x-on:open-logout-modal.window="open = true"
        x-show="open"
        x-cloak
        class="fixed inset-0 z-[99999]"
    >

        <!-- BACKDROP -->
        <div
            class="fixed inset-0 bg-black/70 backdrop-blur-2xl"
            @click="open = false"
        ></div>

        <!-- MODAL WRAPPER (THIS IS THE KEY FIX) -->
        <div class="fixed inset-0 flex items-center justify-center p-4">

            <!-- MODAL -->
            <div
                class="w-full max-w-sm rounded-3xl border border-slate-700 bg-slate-900 p-8 shadow-2xl"
            >

                <h2 class="mb-3 text-center text-2xl font-black text-white">
                    Logout?
                </h2>

                <p class="mb-8 text-center text-slate-300">
                    Are you sure you want to log out of your account?
                </p>

                <div class="flex gap-3">

                    <button
                        @click="open = false"
                        class="flex-1 rounded-xl border border-slate-600 py-3 font-semibold text-slate-300 hover:bg-slate-800"
                    >
                        Cancel
                    </button>

                    <button
                        @click="loading = true; setTimeout(() => document.getElementById('logout-form').submit(), 600)"
                        :disabled="loading"
                        class="flex-1 rounded-xl bg-red-600 py-3 font-bold text-white hover:bg-red-700 flex items-center justify-center gap-2 disabled:opacity-70"
                    >

                        <svg
                            x-show="loading"
                            class="w-4 h-4 animate-spin"
                            viewBox="0 0 24 24"
                            fill="none"
                        >
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                        </svg>

                        <span x-text="loading ? 'Logging out...' : 'Logout'"></span>

                    </button>

                </div>

            </div>

        </div>

    </div>
</div>

