<div wire:key="player-spin-wheel" x-data="{
    open: @js(request('spin') === 'open'), spinning: false, reveal: false, rotation: 0,
    spinDuration: {{ (int)$settings->animation_duration_ms }}, awaitingTransition: false, winningSlot: null, fallbackTimer: null, pollTimer: null,
    visibleAttempts: {{ $availableSpins }}, visibleSajilo: {{ $sajiloPoints }}, visibleBonus: {{ $bonusPoints }},
    show() { this.open = true; document.documentElement.classList.add('overflow-hidden'); this.$nextTick(() => this.$refs.closeButton?.focus()); },
    close() { if (!this.spinning) { this.open = false; this.reveal = false; document.documentElement.classList.remove('overflow-hidden'); this.$nextTick(() => this.$refs.launcher?.focus()); } },
    land(result) {
        this.winningSlot = result.slot; this.spinDuration = result.duration; this.awaitingTransition = true;
        const normalized = ((this.rotation % 360) + 360) % 360;
        const delta = (result.landingAngle - normalized + 360) % 360;
        this.$nextTick(() => requestAnimationFrame(() => {
            this.rotation += (8 * 360) + delta;
            clearTimeout(this.fallbackTimer);
            this.fallbackTimer = setTimeout(() => this.finishAnimation(), this.spinDuration + 750);
        }));
    },
    transitionFinished(event) { if (event.target === this.$refs.disk && event.propertyName === 'transform') this.finishAnimation(); },
    finishAnimation() { if (!this.awaitingTransition) return; this.awaitingTransition = false; clearTimeout(this.fallbackTimer); this.$wire.animationFinished(); },
    startPolling() { this.pollTimer = setInterval(() => { if (!this.spinning && !this.reveal && !this.awaitingTransition) this.$wire.refreshState(); }, 5000); }
}" x-on:spin-wheel-result.window="land($event.detail)"
   x-on:spin-wheel-reveal-state.window="visibleAttempts=$event.detail.attempts;visibleSajilo=$event.detail.sajilo;visibleBonus=$event.detail.bonus;spinning=false;awaitingTransition=false;reveal=true"
   x-on:spin-wheel-display-state.window="if(!spinning&&!reveal){visibleAttempts=$event.detail.attempts;visibleSajilo=$event.detail.sajilo;visibleBonus=$event.detail.bonus}"
   x-init="if(open) document.documentElement.classList.add('overflow-hidden');startPolling()" x-on:spin-wheel-failed.window="spinning=false" x-on:keydown.escape.window="close()">
    <style>
        @keyframes spin-orbit { to { transform: rotate(360deg); } }
        @keyframes spin-shimmer { 0% { transform: translateX(-160%) skewX(-20deg); } 100% { transform: translateX(260%) skewX(-20deg); } }
        @keyframes spin-burst { 0% { transform: scale(.25) rotate(0); opacity: 0; } 35% { opacity: 1; } 100% { transform: scale(1.65) rotate(30deg); opacity: 0; } }
        @keyframes spin-confetti { 0% { transform: translate3d(0,-15vh,0) rotate(0); opacity: 1; } 100% { transform: translate3d(var(--drift),90vh,0) rotate(900deg); opacity: 0; } }
        @keyframes spin-win-bounce { 0%,100% { transform: scale(1); } 45% { transform: scale(1.12) rotate(-1deg); } }
        .spin-segment-disk { background: conic-gradient(from 0deg,#ff2d92 0deg 22.5deg,#6227e9 22.5deg 45deg,#08b8ff 45deg 67.5deg,#ff8a00 67.5deg 90deg,#d81bff 90deg 112.5deg,#0ccf9f 112.5deg 135deg,#ff315b 135deg 157.5deg,#6338ff 157.5deg 180deg,#f4c430 180deg 202.5deg,#008cff 202.5deg 225deg,#f044ff 225deg 247.5deg,#ff7518 247.5deg 270deg,#00bd9d 270deg 292.5deg,#df206f 292.5deg 315deg,#7453ff 315deg 337.5deg,#e6b91e 337.5deg 360deg); }
        .spin-bulbs { animation: spin-orbit 10s linear infinite; }
        .spin-win-text { animation: spin-win-bounce 1.15s ease-in-out infinite; }
        @media (prefers-reduced-motion: reduce) {
            .spin-bulbs,.spin-win-text,.spin-confetti,.spin-shimmer { animation: none !important; }
            [data-spin-disk] { transition-duration: .01ms !important; }
        }
    </style>
    @if($settings->is_enabled && $settings->launcher_enabled)
        <button x-ref="launcher" type="button" @click="show()" class="group fixed bottom-4 left-3 z-[800] flex max-w-[calc(100vw-1.5rem)] items-center gap-2 rounded-full border border-amber-200/80 bg-slate-950/95 p-2 pr-4 shadow-[0_0_18px_#f59e0b,0_0_45px_rgba(217,70,239,.55)] backdrop-blur-xl transition hover:scale-105 sm:bottom-7 sm:left-7" aria-label="Open BrahmaBull Spin and Win">
            <span class="relative block h-16 w-16 shrink-0 overflow-hidden rounded-full ring-2 ring-amber-300 sm:h-20 sm:w-20">
                <img src="{{ $assetUrl }}" alt="Spin Wheel" class="h-full w-full object-cover transition duration-700 group-hover:rotate-180 group-hover:scale-110">
                <span class="absolute inset-0 animate-ping rounded-full ring-4 ring-fuchsia-400/30"></span>
            </span>
            <span class="min-w-0 text-left"><span class="block truncate font-black tracking-wider text-amber-300">SPIN & WIN</span><span class="block truncate text-xs font-bold text-emerald-300" x-text="visibleAttempts>0 ? visibleAttempts+' '+(visibleAttempts===1?'SPIN':'SPINS')+' AVAILABLE' : 'LOCKED'"></span></span>
            <span data-spin-attempt-badge class="absolute -right-1 -top-2 inline-flex h-8 min-w-8 items-center justify-center rounded-full bg-fuchsia-600 px-2 text-sm font-black text-white ring-2 ring-white" x-text="visibleAttempts"></span>
        </button>

        <div x-show="open" x-cloak x-transition.opacity class="fixed inset-0 z-[1200] overflow-x-hidden overflow-y-auto bg-[#040012]/95 px-2 py-3 backdrop-blur-xl sm:px-6 lg:overflow-y-hidden" role="dialog" aria-modal="true" aria-labelledby="spin-wheel-title">
            <div class="pointer-events-none fixed inset-0 overflow-hidden" aria-hidden="true">
                <div class="absolute left-1/2 top-1/2 h-[60vmin] w-[60vmin] -translate-x-1/2 -translate-y-1/2 rounded-full bg-fuchsia-500/20 blur-[90px]"></div>
                @foreach(range(1,18) as $particle)<i class="absolute h-1.5 w-1.5 animate-ping rounded-full {{ $particle%3===0?'bg-amber-300':($particle%3===1?'bg-fuchsia-400':'bg-cyan-300') }}" style="left:{{ ($particle*37)%94+3 }}%;top:{{ ($particle*53)%90+5 }}%;animation-delay:{{ ($particle%7)*.18 }}s"></i>@endforeach
            </div>
            <main class="relative mx-auto flex min-h-[calc(100vh-1.5rem)] w-full max-w-7xl flex-col items-center overflow-hidden rounded-[2rem] border border-amber-300/35 bg-[radial-gradient(circle_at_top,#351064_0%,#10052b_38%,#03010c_78%)] p-3 shadow-[inset_0_0_70px_rgba(236,72,153,.15),0_0_80px_rgba(168,85,247,.4)] sm:p-7 lg:h-[calc(100dvh-1.5rem)] lg:min-h-0">
                <div class="pointer-events-none absolute -top-20 h-48 w-3/4 rounded-full bg-amber-300/15 blur-3xl"></div>
                <button x-ref="closeButton" type="button" @click="close()" :disabled="spinning" class="absolute right-3 top-3 z-50 rounded-full border border-white/20 bg-black/30 px-3 py-1 text-2xl text-slate-300 hover:text-white disabled:opacity-30" aria-label="Close Spin Wheel">&times;</button>
                <div class="relative mt-1">
                    <div class="absolute inset-0 animate-pulse rounded-full bg-amber-300/25 blur-xl"></div>
                    <img src="{{ asset('images/logo-brahma.png') }}" alt="BrahmaBull" class="relative h-16 w-40 object-contain drop-shadow-[0_0_15px_rgba(251,191,36,.7)] sm:h-20 sm:w-52">
                </div>
                <p class="text-[10px] font-black uppercase tracking-[.45em] text-amber-300 sm:text-xs">BrahmaBull Rewards</p>
                <h2 id="spin-wheel-title" class="mt-1 bg-gradient-to-r from-amber-200 via-yellow-300 to-fuchsia-400 bg-clip-text text-center text-3xl font-black text-transparent drop-shadow-lg sm:text-5xl">SPIN & WIN</h2>
                <div class="mt-2 flex flex-wrap justify-center gap-2"><p class="rounded-full border border-amber-300/25 bg-white/5 px-4 py-2 text-sm font-bold">Available spins: <strong class="text-amber-300" x-text="visibleAttempts"></strong></p><p data-spin-sajilo-balance class="rounded-full border border-cyan-300/25 bg-white/5 px-4 py-2 text-sm font-bold">Sajilo: <strong class="text-cyan-300" x-text="visibleSajilo"></strong></p><p data-spin-bonus-balance class="rounded-full border border-fuchsia-300/25 bg-white/5 px-4 py-2 text-sm font-bold">Pending Bonus: <strong class="text-fuchsia-300" x-text="visibleBonus"></strong></p></div>

                <div class="relative my-4 aspect-square w-[min(94vw,660px)] shrink-0 transition duration-500 sm:my-5 lg:w-[min(660px,calc(100dvh-420px))]" :class="reveal ? 'blur-md brightness-[.28] scale-[.96]' : ''">
                    <div class="absolute -inset-[5%] animate-pulse rounded-full bg-gradient-to-r from-fuchsia-500/40 via-amber-300/40 to-cyan-400/40 blur-2xl"></div>
                    <div class="spin-bulbs absolute -inset-[3%] rounded-full" aria-hidden="true">
                        @foreach(range(0,31) as $bulb)<i class="absolute left-1/2 top-1/2 h-2.5 w-2.5 rounded-full bg-amber-100 shadow-[0_0_10px_#fde047]" style="transform:rotate({{ $bulb*11.25 }}deg) translateY(calc(-1 * clamp(150px, 44vw, 340px)))"></i>@endforeach
                    </div>
                    <div class="absolute left-1/2 top-[-2%] z-40 -translate-x-1/2 drop-shadow-[0_0_12px_#fbbf24]" aria-hidden="true">
                        <div class="h-0 w-0 border-x-[20px] border-t-[42px] border-x-transparent border-t-amber-200 sm:border-x-[26px] sm:border-t-[54px]"></div>
                    </div>
                    <div wire:ignore x-ref="disk" data-spin-disk :data-winning-slot="winningSlot" :data-current-angle="rotation" @transitionend="transitionFinished($event)" class="absolute inset-[3%] overflow-hidden rounded-full border-[7px] border-amber-300 shadow-[inset_0_0_30px_rgba(0,0,0,.8),0_0_25px_#f59e0b,0_0_60px_rgba(217,70,239,.7)] sm:border-[10px]" style="will-change:transform;backface-visibility:hidden;transform:translate3d(0,0,0) rotate3d(0,0,1,0deg)" :style="`transform:translate3d(0,0,0) rotate3d(0,0,1,${rotation}deg);transition-property:transform;transition-duration:${spinning?spinDuration:0}ms;transition-timing-function:cubic-bezier(.12,.72,.18,1)`">
                        <div class="spin-segment-disk absolute inset-0"></div>
                        <img src="{{ $assetUrl }}" alt="" aria-hidden="true" class="absolute inset-0 h-full w-full object-cover opacity-25 mix-blend-screen">
                        <div class="spin-shimmer absolute inset-y-0 left-0 z-10 w-1/3 bg-gradient-to-r from-transparent via-white/35 to-transparent blur-md" style="animation:spin-shimmer 2.8s linear infinite"></div>
                        @foreach($visualSegments as $segment)
                            @php($centerAngle=(($segment['slot']-1)*22.5)+11.25)
                            @php($cssAngle=$centerAngle-90)
                            <div data-wheel-segment="{{ $segment['type'] }}" data-center-angle="{{ $centerAngle }}" class="absolute inset-0 z-20 origin-center" style="transform:rotate({{ $cssAngle }}deg)">
                                <span class="absolute left-[79%] top-1/2 flex w-[clamp(54px,16%,82px)] -translate-x-1/2 -translate-y-1/2 items-center justify-center text-center font-black uppercase leading-[1.05] text-white drop-shadow-[0_2px_3px_#000] {{ $segment['type']==='featured'?'text-[7px] sm:text-[9px]':'text-base sm:text-2xl' }}" style="transform:translate(-50%,-50%) rotate({{ -$cssAngle }}deg)">{{ $segment['label'] }}</span>
                            </div>
                        @endforeach
                    </div>
                    <div class="absolute left-1/2 top-1/2 z-30 flex h-[19%] w-[19%] -translate-x-1/2 -translate-y-1/2 items-center justify-center overflow-hidden rounded-full border-4 border-amber-100 bg-gradient-to-br from-yellow-100 via-amber-400 to-orange-600 shadow-[inset_0_0_18px_#92400e,0_0_20px_#fde047]">
                        <img src="{{ asset('images/logo.png') }}" alt="BrahmaBull" class="h-[72%] w-[72%] object-contain drop-shadow-[0_0_12px_rgba(255,255,255,.9)]">
                    </div>
                </div>

                @error('spin')<p class="mb-3 rounded-xl bg-red-500/15 px-4 py-2 text-sm text-red-200">{{ $message }}</p>@enderror
                <button type="button" data-spin-action wire:click="spin" @click="spinning=true;reveal=false" :disabled="spinning || reveal || {{ $availableSpins }} < 1" wire:loading.attr="disabled" wire:target="spin" class="relative shrink-0 overflow-hidden rounded-full bg-gradient-to-r from-amber-400 via-yellow-200 to-orange-500 px-12 py-4 font-black tracking-[.2em] text-slate-950 shadow-[0_0_35px_rgba(251,191,36,.75)] transition hover:scale-105 disabled:cursor-not-allowed disabled:grayscale">
                    <span x-show="!spinning">{{ $availableSpins>0?'SPIN NOW':'NO SPINS AVAILABLE' }}</span><span x-show="spinning" x-cloak>THE WHEEL IS SPINNING...</span>
                </button>

                @if($result)
                    <section x-show="reveal" x-cloak x-transition.scale class="spin-result-card absolute inset-x-3 top-1/2 z-[70] mx-auto w-auto max-w-2xl -translate-y-1/2 overflow-hidden rounded-[2rem] border-2 border-amber-300/60 bg-gradient-to-br from-fuchsia-600/95 via-purple-950/95 to-slate-950/95 p-6 text-center shadow-[0_0_80px_rgba(251,191,36,.65)] backdrop-blur-xl sm:inset-x-8 sm:p-9">
                        @if($settings->celebration_enabled)
                            <div class="pointer-events-none absolute inset-0" aria-hidden="true"><div class="absolute inset-[12%] rounded-full border-4 border-amber-200/50" style="animation:spin-burst 1.4s ease-out infinite"></div>@foreach(range(1,28) as $piece)<i class="spin-confetti absolute -top-4 h-3 w-1.5 {{ $piece%4===0?'bg-amber-300':($piece%4===1?'bg-fuchsia-400':($piece%4===2?'bg-cyan-300':'bg-white')) }}" style="left:{{ ($piece*41)%96+2 }}%;--drift:{{ (($piece%7)-3)*18 }}px;animation:spin-confetti {{ 1.8+($piece%5)*.2 }}s linear {{ ($piece%6)*.08 }}s infinite"></i>@endforeach</div>
                        @endif
                        <p class="relative text-sm font-black tracking-[.42em] text-amber-300">{{ $result['type']==='try_again' ? 'TRY AGAIN!' : 'YOU WON!' }}</p>
                        <p class="spin-win-text relative mt-3 text-4xl font-black uppercase text-white drop-shadow-[0_0_18px_rgba(251,191,36,.8)] sm:text-6xl">{{ $result['name'] }}</p>
                        <p class="relative mt-2 text-lg font-bold uppercase text-fuchsia-200">{{ Str::headline($result['type']) }}</p>
                        @if($result['value'])<p class="relative mt-2 text-3xl font-black text-amber-200">{{ $result['value'] }}</p>@endif
                        @if($result['description'])<p class="relative mt-3 text-sm text-slate-200">{{ $result['description'] }}</p>@endif
                        <p class="relative mt-4 font-bold">{{ $result['type']==='try_again' ? 'Better luck on your next spin.' : 'Congratulations, '.auth()->user()->name.'!' }}</p>
                        <p class="relative mt-2 text-emerald-300">{{ $availableSpins }} {{ Str::plural('spin',$availableSpins) }} remaining</p>
                        <button type="button" @click="reveal=false" class="relative mt-5 rounded-full border border-white/30 bg-white/10 px-6 py-2 font-bold hover:bg-white/20">Continue</button>
                    </section>
                @endif
                @if($settings->show_recent_win)
                    <section class="mt-4 w-full max-w-2xl rounded-2xl border border-white/10 bg-white/5 p-3 text-center lg:absolute lg:bottom-5 lg:right-5 lg:mt-0 lg:w-64"><h3 class="text-xs font-black tracking-[.3em] text-amber-300">TODAY'S WINS</h3><div class="mt-2 flex flex-wrap justify-center gap-2">@forelse($todayWins as $win)<span wire:key="today-spin-{{ $win['id'] }}" class="rounded-full bg-slate-950/70 px-3 py-1 text-xs font-bold">{{ $win['label'] }}</span>@empty<span class="text-xs text-slate-500">No spins yet today.</span>@endforelse</div></section>
                @endif
                <p class="mt-5 max-w-2xl text-center text-xs text-slate-400 lg:absolute lg:bottom-5 lg:left-5 lg:mt-0 lg:w-64 lg:text-left">{{ $settings->terms_text }}</p>
            </main>
        </div>
    @endif
</div>
