<section class="relative overflow-hidden" wire:poll.5000ms="next">

    <!-- Background glow -->
    <div class="absolute -left-40 top-0 h-96 w-96 rounded-full bg-purple-700/20 blur-3xl"></div>
    <div class="absolute right-0 top-20 h-96 w-96 rounded-full bg-indigo-700/20 blur-3xl"></div>

    <div class="mx-auto max-w-7xl px-6 py-28">

        <div class="relative grid items-center gap-16 lg:grid-cols-2">

            <!-- LEFT CONTENT -->
            <div class="transition-all duration-500">

                <p class="mb-4 text-sm font-bold uppercase tracking-[0.3em] text-purple-400">
                    BrahmaBull Gaming Club
                </p>

                <h1 class="mb-6 text-6xl font-black leading-tight lg:text-7xl">
                    {{ $slides[$active]['title'] }}
                </h1>

                <p class="mb-10 max-w-xl text-lg text-slate-300">
                    {{ $slides[$active]['subtitle'] }}
                </p>

                <div class="flex flex-wrap gap-4">

                    {{-- ========================= --}}
                    {{-- GUEST USERS --}}
                    {{-- ========================= --}}
                    @guest

                        @if($active === 0)

                            <a href="#games"
                               class="rounded-2xl bg-gradient-to-r from-purple-600 to-indigo-600 px-8 py-4 font-bold">
                                Explore Games
                            </a>

                            <a href="/register"
                               class="rounded-2xl border border-slate-700 px-8 py-4 font-bold">
                                Join Us
                            </a>

                        @elseif($active === 1)

                            <a href="#about"
                               class="rounded-2xl bg-gradient-to-r from-purple-600 to-indigo-600 px-8 py-4 font-bold">
                                Learn More
                            </a>

                            <a href="/login"
                               class="rounded-2xl border border-slate-700 px-8 py-4 font-bold">
                                Start Playing
                            </a>

                        @elseif($active === 2)

                            <a href="/login"
                               class="rounded-2xl bg-gradient-to-r from-purple-600 to-indigo-600 px-8 py-4 font-bold">
                                Invite Friends
                            </a>

                            <a href="/login"
                               class="rounded-2xl border border-slate-700 px-8 py-4 font-bold">
                                Play Now
                            </a>

                        @endif

                    @endguest



                    {{-- ========================= --}}
                    {{-- PLAYER --}}
                    {{-- ========================= --}}
                    @auth

                        @if(auth()->user()->hasRole('player'))

                            @if($active === 0)

                                <a href="/catalog"
                                   class="rounded-2xl bg-gradient-to-r from-purple-600 to-indigo-600 px-8 py-4 font-bold">
                                    Explore Games
                                </a>

                                <a href="/catalog"
                                   class="rounded-2xl border border-slate-700 px-8 py-4 font-bold">
                                    Play Now
                                </a>

                            @elseif($active === 1)

                                <a href="/profile"
                                   class="rounded-2xl bg-gradient-to-r from-purple-600 to-indigo-600 px-8 py-4 font-bold">
                                    My Details
                                </a>

                                <a href="/catalog"
                                   class="rounded-2xl border border-slate-700 px-8 py-4 font-bold">
                                    Play Now
                                </a>

                            @elseif($active === 2)

                                <button
                                    type="button"
                                    @click="$dispatch('open-referral-modal')"
                                    class="rounded-2xl bg-gradient-to-r from-purple-600 to-indigo-600 px-8 py-4 font-bold"
                                >
                                    Invite Friends
                                </button>

                                <a href="/catalog"
                                   class="rounded-2xl border border-slate-700 px-8 py-4 font-bold">

                                    Start Playing

                                </a>

                            @endif

                        @endif

                    @endauth



                    {{-- ========================= --}}
                    {{-- AGENT --}}
                    {{-- ========================= --}}
                    @auth

                        @if(auth()->user()->hasRole('agent'))

                            <a href="/agent"
                               class="rounded-2xl bg-gradient-to-r from-purple-600 to-indigo-600 px-8 py-4 font-bold">

                                Dashboard

                            </a>

                        @endif

                    @endauth



                    {{-- ========================= --}}
                    {{-- ADMIN --}}
                    {{-- ========================= --}}
                    @auth

                        @if(auth()->user()->hasRole('admin'))

                            <a href="/admin"
                               class="rounded-2xl bg-gradient-to-r from-purple-600 to-indigo-600 px-8 py-4 font-bold">

                                Dashboard

                            </a>

                        @endif

                    @endauth

                </div>
            </div>

            <!-- RIGHT VISUAL -->
            <div class="transition-all duration-500">

                <div class="rounded-3xl border border-slate-800 bg-slate-900/60 p-6 backdrop-blur">

                    <div class="aspect-[4/3] overflow-hidden rounded-2xl">

                        <img
                            src="{{ $slides[$active]['image'] }}"
                            class="h-full w-full object-cover transition duration-500"
                            alt="slide image"
                        />

                    </div>

                </div>

            </div>



        </div>

    </div>
    @auth
        @if(auth()->user()->hasRole('player'))

            <div
                x-data="{
        open:false,
        copied:false,
        copy(text){
            navigator.clipboard.writeText(text);
            this.copied = true;
            setTimeout(() => this.copied = false, 2000);
        }
    }"
                x-on:open-referral-modal.window="open=true"
                x-show="open"
                x-cloak
                class="fixed inset-0 z-[9999]"
            >

                <!-- Backdrop -->
                <div
                    class="absolute inset-0 bg-black/70 backdrop-blur-sm"
                    @click="open=false"
                ></div>

                <!-- Modal -->
                <div class="absolute inset-0 flex items-center justify-center p-4">

                    <div
                        class="w-full max-w-lg rounded-3xl border border-slate-700 bg-slate-900 p-8 shadow-2xl"
                    >

                        <h2 class="text-2xl font-black text-white mb-6">
                            Invite Friends
                        </h2>

                        <!-- Referral ID -->
                        <div class="mb-5">

                            <label class="text-sm text-slate-400 block mb-2">
                                Referral Code
                            </label>

                            <div class="flex gap-2">

                                <input
                                    readonly
                                    value="{{ auth()->user()->referral_code }}"
                                    class="flex-1 rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-white"
                                >

                                <button
                                    @click="copy('{{ auth()->user()->referral_code }}')"
                                    class="rounded-xl bg-purple-600 px-4 py-3 font-semibold"
                                >
                                    Copy
                                </button>

                            </div>

                        </div>

                        <!-- Referral Link -->
                        <div class="mb-6">

                            <label class="text-sm text-slate-400 block mb-2">
                                Invite Link
                            </label>

                            <div class="flex gap-2">

                                <input
                                    readonly
                                    value="{{ route('register',['ref'=>auth()->user()->referral_code]) }}"
                                    class="flex-1 rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-white"
                                >

                                <button
                                    @click="copy('{{ route('register',['ref'=>auth()->user()->referral_code]) }}')"
                                    class="rounded-xl bg-purple-600 px-4 py-3 font-semibold"
                                >
                                    Copy
                                </button>

                            </div>

                        </div>

                        <!-- Success Message -->
                        <div
                            x-show="copied"
                            x-transition
                            class="mb-4 rounded-xl border border-green-500/30 bg-green-500/10 p-3 text-sm text-green-400"
                        >
                            Copied successfully.
                        </div>

                        <!-- Description -->
                        <div
                            class="mb-6 rounded-xl border border-slate-800 bg-slate-950 p-4 text-sm text-slate-300"
                        >
                            Send this referral code or the invite link to your friend.
                            After they register and start playing, you will receive the
                            referral bonus.
                        </div>

                        <!-- Close -->
                        <button
                            @click="open=false"
                            class="w-full rounded-xl border border-slate-700 py-3 font-semibold hover:bg-slate-800"
                        >
                            Close
                        </button>

                    </div>

                </div>

            </div>

        @endif
    @endauth
</section>
