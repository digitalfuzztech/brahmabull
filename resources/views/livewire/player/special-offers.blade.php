<div x-on:keydown.escape.window="$wire.close()">
    @if($offers->isNotEmpty())
        <div class="bb-offers-launcher fixed bottom-28 right-4 z-[8999] sm:right-6">
            <button type="button" wire:click="toggleList" class="bb-offers-launcher__button" aria-label="Open special offers" aria-expanded="{{ $listOpen ? 'true' : 'false' }}">
                <span class="bb-offers-launcher__label">OFFERS</span>
                <span class="bb-offers-launcher__spark" aria-hidden="true">✦</span>
                <img src="{{ asset('images/ui/casino/offers-mascot.png') }}" alt="" class="bb-offers-launcher__mascot">
            </button>
        </div>

        @if($listOpen)
            <section role="dialog" aria-modal="true" aria-labelledby="special-offers-heading" class="bb-offers-panel fixed bottom-28 right-3 z-[9998] w-[calc(100vw-1.5rem)] max-w-sm overflow-hidden rounded-3xl sm:bottom-32 sm:right-28">
                <div class="relative p-5">
                    <span class="bb-offers-panel__sparkle" aria-hidden="true">✦</span>
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-[.34em] text-amber-300">BrahmaBull</p>
                            <h2 id="special-offers-heading" class="mt-1 text-xl font-black tracking-wide text-white">SPECIAL OFFERS</h2>

                            <p class="text-[8px] text-white">Click to view offer</p>
                        </div>
                        <button type="button" wire:click="close" class="bb-offers-close" aria-label="Close special offers">&times;</button>
                    </div>
                    <div class="mt-4 space-y-3">
                        @foreach($offers as $offer)
                            <button type="button" wire:key="player-offer-{{ $offer->id }}" wire:click="showOffer({{ $offer->id }})" class="bb-offers-card w-full text-left">
                                <span class="block text-sm font-black text-white">{{ $offer->name }}</span>
                                <span class="mt-1 block text-xs leading-5 text-purple-100">{{ \Illuminate\Support\Str::limit($offer->description, 90) }}</span>
                                <span class="mt-2 block text-[10px] font-bold uppercase tracking-wider text-amber-200">Through {{ $offer->ends_at->format('M j, Y') }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        @if($selectedOffer)
            <div class="fixed inset-0 z-[10020] overflow-y-auto bg-slate-950/80 p-3 backdrop-blur-md sm:p-6" role="dialog" aria-modal="true" aria-labelledby="special-offer-title">
                <button type="button" wire:click="close" class="absolute inset-0 h-full w-full cursor-default" aria-label="Close offer detail"></button>
                <div class="relative flex min-h-full items-center justify-center">
                    <article class="bb-offer-detail relative w-full max-w-4xl overflow-hidden rounded-[2rem] px-5 py-10 text-center sm:px-24 sm:py-14">
                        <button type="button" wire:click="close" class="bb-offers-close absolute right-4 top-4 z-20" aria-label="Close offer detail">&times;</button>
                        <div class="bb-offer-celebration" aria-hidden="true">
                            @foreach(range(1, 14) as $piece)
                                <i class="{{ $piece <= 4 ? 'bb-offer-ribbon' : 'bb-offer-confetti' }}" style="--x: {{ (($piece * 17) % 92) + 4 }}%; --delay: -{{ ($piece % 7) * 0.43 }}s; --duration: {{ 4.2 + (($piece % 5) * 0.36) }}s; --drift: {{ (($piece % 5) - 2) * 18 }}px; --turn: {{ 80 + ($piece * 31) }}deg;"></i>
                            @endforeach
                            @foreach(range(1, 5) as $spark)
                                <i class="bb-offer-spark" style="--x: {{ 10 + (($spark * 19) % 80) }}%; --y: {{ 12 + (($spark * 23) % 72) }}%; --delay: -{{ $spark * 0.7 }}s;"></i>
                            @endforeach
                            @foreach([[16, 24], [82, 30], [72, 78]] as [$x, $y])
                                <i class="bb-offer-firework" style="--x: {{ $x }}%; --y: {{ $y }}%; --delay: -{{ $loop->index * 1.8 }}s;"></i>
                            @endforeach
                        </div>
                        <img src="{{ asset('images/ui/casino/brahma-mascot-left.png') }}" alt="" class="bb-offer-detail__mascot bb-offer-detail__mascot--left">
                        <img src="{{ asset('images/ui/casino/brahma-mascot-right.png') }}" alt="" class="bb-offer-detail__mascot bb-offer-detail__mascot--right">
                        <div class="relative z-10 mx-auto max-h-[72vh] max-w-2xl overflow-y-auto px-2 py-2 custom-scrollbar">
                            <p class="text-xs font-black uppercase tracking-[.4em] text-amber-300">Limited Promotion</p>
                            <h2 id="special-offer-title" class="mt-4 text-3xl font-black uppercase text-white sm:text-5xl">{{ $selectedOffer->name }}</h2>
                            <div class="mx-auto mt-4 h-px w-32 bg-gradient-to-r from-transparent via-amber-300 to-transparent"></div>
                            <p class="mt-6 whitespace-pre-line text-sm leading-7 text-purple-50 sm:text-base">{{ $selectedOffer->description }}</p>
                            <p class="mt-5 text-xs font-bold uppercase tracking-wider text-amber-200">Valid {{ $selectedOffer->starts_at->format('M j') }} – {{ $selectedOffer->ends_at->format('M j, Y') }}</p>
                            <a href="{{ route('games') }}" class="bb-offer-detail__cta mt-8 inline-flex min-h-14 items-center justify-center rounded-xl px-10 text-sm font-black tracking-[.18em] text-slate-950">PLAY GAME</a>
                        </div>
                    </article>
                </div>
            </div>
        @endif
    @endif
</div>
