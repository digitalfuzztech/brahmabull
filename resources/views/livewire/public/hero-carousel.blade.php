<section
    class="bb-casino-hero"
    wire:poll.5000ms="next"
>

    {{-- ===================================================== --}}
    {{-- CINEMATIC HERO BACKGROUND                             --}}
    {{-- ===================================================== --}}

    <img
        src="{{ asset('images/ui/casino/hero-casino-bg.png') }}"
        alt=""
        aria-hidden="true"
        class="bb-casino-hero-bg"
    >

    <div class="bb-casino-hero-shade"></div>
    <div class="bb-casino-hero-bottom"></div>


    {{-- Decorative side signs from generated assets --}}
    <img
        src="{{ asset('images/ui/casino/hero-marquee-left.png') }}"
        alt=""
        aria-hidden="true"
        class="bb-hero-marquee-left bb-marquee-enter-left hidden 2xl:block"
    >

    <img
        src="{{ asset('images/ui/casino/hero-marquee-right.png') }}"
        alt=""
        aria-hidden="true"
        class="bb-hero-marquee-right bb-marquee-enter-right hidden 2xl:block"
    >


    {{-- ===================================================== --}}
    {{-- REAL HERO CONTENT                                     --}}
    {{-- ===================================================== --}}

    <div class="relative mx-auto max-w-[1380px] px-5 md:px-7">

        <div
            class="grid min-h-[630px] items-center gap-8
                   py-12 lg:grid-cols-[1.02fr_0.98fr]
                   lg:gap-8 lg:py-6"
        >

            {{-- ================================================= --}}
            {{-- LEFT CONTENT                                      --}}
            {{-- ================================================= --}}

            <div class="relative z-20 max-w-[720px]">

                {{-- ONLY THIS PART CHANGES WITH EACH SLIDE --}}
                <div
                    wire:key="hero-copy-{{ $active }}"
                    class="bb-hero-copy-cycle"
                >

                    <p
                        class="mb-5 text-[11px] font-black uppercase
                   tracking-[0.34em] text-fuchsia-300
                   sm:text-xs"
                    >
                        BrahmaBull Gaming Club
                    </p>


                    <h1
                        class="bb-hero-title text-[3.45rem]
                   sm:text-[4.8rem]
                   lg:text-[5.45rem]
                   xl:text-[6.15rem]"
                        aria-label="{{ $slides[$active]['title'] }}"
                    >

                        @if($active === 0)

                            <span class="bb-hero-title-white block">
                    Play. Win.
                </span>

                            <span class="bb-hero-title-gold block">
                    Dominate.
                </span>

                        @elseif($active === 1)

                            <span class="bb-hero-title-white block">
                    Earn Real
                </span>

                            <span class="bb-hero-title-gold block">
                    Rewards
                </span>

                        @elseif($active === 2)

                            <span class="bb-hero-title-white block">
                    Referral
                </span>

                            <span class="bb-hero-title-gold block">
                    Bonuses
                </span>

                        @endif

                    </h1>


                    <p
                        class="mt-6 max-w-[590px] text-[15px]
                   font-medium leading-7 text-slate-200
                   sm:text-[17px]"
                    >
                        {{ $slides[$active]['subtitle'] }}
                    </p>


                    <div class="mt-8 flex flex-wrap gap-4">

                        {{-- GUEST --}}
                        @guest

                            @if($active === 0)

                                <a
                                    href="#games"
                                    class="bb-hero-primary inline-flex min-h-[54px]
                       items-center justify-center rounded-xl
                       px-7 text-sm font-black"
                                >
                                    Explore Games
                                </a>

                                <a
                                    href="/register"
                                    class="bb-hero-secondary inline-flex min-h-[54px]
                       items-center justify-center rounded-xl
                       px-7 text-sm font-black"
                                >
                                    Join Us
                                </a>

                            @elseif($active === 1)

                                <a
                                    href="#about"
                                    class="bb-hero-primary inline-flex min-h-[54px]
                       items-center justify-center rounded-xl
                       px-7 text-sm font-black"
                                >
                                    Learn More
                                </a>

                                <a
                                    href="/login"
                                    class="bb-hero-secondary inline-flex min-h-[54px]
                       items-center justify-center rounded-xl
                       px-7 text-sm font-black"
                                >
                                    Start Playing
                                </a>

                            @elseif($active === 2)

                                <a
                                    href="/login"
                                    class="bb-hero-primary inline-flex min-h-[54px]
                       items-center justify-center rounded-xl
                       px-7 text-sm font-black"
                                >
                                    Invite Friends
                                </a>

                                <a
                                    href="/login"
                                    class="bb-hero-secondary inline-flex min-h-[54px]
                       items-center justify-center rounded-xl
                       px-7 text-sm font-black"
                                >
                                    Play Now
                                </a>

                            @endif

                        @endguest


                        {{-- PLAYER --}}
                        @auth

                            @if(auth()->user()->hasRole('player'))

                                @if($active === 0)

                                    <a
                                        href="/catalog"
                                        class="bb-hero-primary inline-flex min-h-[54px]
                           items-center justify-center rounded-xl
                           px-7 text-sm font-black"
                                    >
                                        Explore Games
                                    </a>

                                    <a
                                        href="/catalog"
                                        class="bb-hero-secondary inline-flex min-h-[54px]
                           items-center justify-center rounded-xl
                           px-7 text-sm font-black"
                                    >
                                        Play Now
                                    </a>

                                @elseif($active === 1)

                                    <a
                                        href="/profile"
                                        class="bb-hero-primary inline-flex min-h-[54px]
                           items-center justify-center rounded-xl
                           px-7 text-sm font-black"
                                    >
                                        My Details
                                    </a>

                                    <a
                                        href="/catalog"
                                        class="bb-hero-secondary inline-flex min-h-[54px]
                           items-center justify-center rounded-xl
                           px-7 text-sm font-black"
                                    >
                                        Play Now
                                    </a>

                                @elseif($active === 2)

                                    <button
                                        type="button"
                                        @click="$dispatch('open-referral-modal')"
                                        class="bb-hero-primary inline-flex min-h-[54px]
                           items-center justify-center rounded-xl
                           px-7 text-sm font-black"
                                    >
                                        Invite Friends
                                    </button>

                                    <a
                                        href="/catalog"
                                        class="bb-hero-secondary inline-flex min-h-[54px]
                           items-center justify-center rounded-xl
                           px-7 text-sm font-black"
                                    >
                                        Start Playing
                                    </a>

                                @endif

                            @endif

                        @endauth


                        {{-- AGENT --}}
                        @auth

                            @if(auth()->user()->hasRole('agent'))

                                <a
                                    href="/agent"
                                    class="bb-hero-primary inline-flex min-h-[54px]
                       items-center justify-center rounded-xl
                       px-7 text-sm font-black"
                                >
                                    Dashboard
                                </a>

                            @endif

                        @endauth


                        {{-- ADMIN --}}
                        @auth

                            @if(auth()->user()->hasRole('admin'))

                                <a
                                    href="/admin"
                                    class="bb-hero-primary inline-flex min-h-[54px]
                       items-center justify-center rounded-xl
                       px-7 text-sm font-black"
                                >
                                    Dashboard
                                </a>

                            @endif

                        @endauth

                    </div>

                </div>


                {{-- ================================================= --}}
                {{-- STATIC FEATURE STRIP                              --}}
                {{-- DO NOT PUT THIS INSIDE bb-hero-copy-cycle         --}}
                {{-- ================================================= --}}

                <div
                    class="mt-10 grid max-w-[650px]
               border-t border-amber-400/15
               sm:grid-cols-3"
                >

                    <div class="bb-hero-benefit">

            <span class="bb-hero-benefit-icon">
                ✦
            </span>

                        <div>
                            <div class="text-[14px] font-black uppercase tracking-wider text-white">
                                Rewards
                            </div>

                            <div class="mt-0.5 text-[12px] text-slate-400">
                                Amazing bonuses and rewards
                            </div>
                        </div>

                    </div>


                    <div class="bb-hero-benefit">

            <span class="bb-hero-benefit-icon">
                ⚡
            </span>

                        <div>
                            <div class="text-[14px] font-black uppercase tracking-wider text-white">
                                Fast Access
                            </div>

                            <div class="mt-0.5 text-[12px] text-slate-400">
                                Fast account setup and support
                            </div>
                        </div>

                    </div>


                    <div class="bb-hero-benefit">

            <span class="bb-hero-benefit-icon">
                ◆
            </span>

                        <div>
                            <div class="text-[14px] font-black uppercase tracking-wider text-white">
                                Trusted
                            </div>

                            <div class="mt-0.5 text-[12px] text-slate-400">
                                Smooth and secure experience
                            </div>
                        </div>

                    </div>

                </div>

            </div>


            {{-- ================================================= --}}
            {{-- RIGHT CASINO FEATURE ART                           --}}
            {{-- ================================================= --}}

            <div
                class="relative z-10 flex items-center justify-center
                       lg:justify-end"
            >

                <div
                    class="pointer-events-none absolute
                           h-[420px] w-[420px]
                           rounded-full bg-purple-600/20
                           blur-[110px]"
                ></div>

                <div class="bb-feature-art-enter relative z-10">

                    <img
                        src="{{ asset('images/ui/casino/hero-feature-art.png') }}"
                        alt=""
                        aria-hidden="true"
                        class="bb-hero-feature-art"
                    >

                </div>

            </div>

        </div>

    </div>
    @auth
        @if(auth()->user()->hasRole('player'))
            @teleport('body')
            <div>
            <div
                x-data="{
        open:false,
        copied:false,
        setOpen(value){
            this.open = value;
            document.body.classList.toggle('overflow-hidden', value);
        },
        copy(text){
            navigator.clipboard.writeText(text);
            this.copied = true;
            setTimeout(() => this.copied = false, 2000);
        }
                }"
                x-init="document.addEventListener('livewire:navigating', () => document.body.classList.remove('overflow-hidden'), { once: true })"
                x-on:open-referral-modal.window="setOpen(true)"
                x-on:keydown.escape.window="setOpen(false)"
                x-show="open"
                x-cloak
                class="bb-player-form-overlay bb-invite-overlay-root fixed inset-0 z-[100000] overflow-y-auto"
            >

                <!-- Backdrop -->
                <div
                    class="bb-invite-backdrop fixed inset-0 z-0"
                    @click="setOpen(false)"
                ></div>

                <!-- Modal -->
                <div class="relative z-10 flex min-h-full items-center justify-center p-4">

                    <div
                        role="dialog"
                        aria-modal="true"
                        aria-labelledby="invite-friends-title"
                        class="bb-player-form-shell relative w-full max-w-lg overflow-hidden rounded-2xl border border-slate-700 bg-slate-900 shadow-2xl"
                    >

                        <div class="bb-player-form-header flex items-center justify-between border-b border-slate-800 p-5">
                            <div>
                                <p class="bb-player-form-kicker">Referral Rewards</p>
                                <h2 id="invite-friends-title" class="text-xl font-black text-white">Invite Friends</h2>
                                <p class="bb-player-form-description">Share your code or personal invite link.</p>
                            </div>
                            <button type="button" @click="setOpen(false)" class="bb-player-form-close" aria-label="Close invite friends modal">&times;</button>
                        </div>

                        <div class="bb-player-form-body space-y-5 p-5">

                        <!-- Referral ID -->
                        <div>

                            <label class="text-sm text-slate-400 block mb-2">
                                Referral Code
                            </label>

                            <div class="flex flex-col gap-2 sm:flex-row">

                                <input
                                    readonly
                                    value="{{ auth()->user()->referral_code }}"
                                    class="min-w-0 flex-1 rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-white"
                                >

                                <button
                                    @click="copy('{{ auth()->user()->referral_code }}')"
                                    class="bb-player-form-primary rounded-xl px-5 py-3 font-bold"
                                >
                                    Copy
                                </button>

                            </div>

                        </div>

                        <!-- Referral Link -->
                        <div>

                            <label class="text-sm text-slate-400 block mb-2">
                                Invite Link
                            </label>

                            <div class="flex flex-col gap-2 sm:flex-row">

                                <input
                                    readonly
                                    value="{{ route('register',['ref'=>auth()->user()->referral_code]) }}"
                                    class="min-w-0 flex-1 rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-white"
                                >

                                <button
                                    @click="copy('{{ route('register',['ref'=>auth()->user()->referral_code]) }}')"
                                    class="bb-player-form-primary rounded-xl px-5 py-3 font-bold"
                                >
                                    Copy
                                </button>

                            </div>

                        </div>

                        <!-- Success Message -->
                        <div
                            x-show="copied"
                            x-transition
                            class="bb-player-form-success rounded-xl border border-green-500/30 bg-green-500/10 p-3 text-sm text-green-300"
                        >
                            Copied successfully.
                        </div>

                        <!-- Description -->
                        <div
                            class="rounded-xl border border-purple-500/20 bg-purple-950/20 p-4 text-sm leading-6 text-slate-300"
                        >
                            Send this referral code or the invite link to your friend.
                            After they register and start playing, you will receive the
                            referral bonus.
                        </div>

                        </div>

                        <div class="bb-player-form-footer border-t border-slate-800 p-5">
                        <button
                            type="button"
                            @click="setOpen(false)"
                            class="bb-player-form-secondary w-full rounded-xl py-3 font-semibold"
                        >
                            Close
                        </button>
                        </div>

                    </div>

                </div>

            </div>
            </div>
            @endteleport
        @endif
    @endauth
</section>
