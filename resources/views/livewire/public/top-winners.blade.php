<section class="relative overflow-hidden bg-slate-950 py-14 md:py-16" aria-labelledby="top-winners-title">
    <div class="pointer-events-none absolute inset-x-0 top-1/2 h-64 -translate-y-1/2 bg-gradient-to-r from-transparent via-purple-600/10 to-transparent blur-3xl" aria-hidden="true"></div>

    <div class="relative mx-auto max-w-[1180px] px-5 md:px-7">
        <header data-bb-reveal class="text-center">
            <p class="text-xs font-black uppercase tracking-[0.32em] text-purple-300">Players With Most Wins</p>
            <h2 id="top-winners-title" class="mt-2 text-3xl font-black tracking-tight text-white md:text-4xl">Top Winners</h2>
        </header>

        <div data-bb-reveal class="mx-auto mt-7 flex max-w-2xl rounded-2xl border border-purple-500/25 bg-slate-900/70 p-1.5 backdrop-blur-xl" style="--bb-reveal-delay: 80ms" role="tablist" aria-label="Top Winners period">
            @foreach([
                'last_7_days' => 'Last 7 Days',
                'this_month' => 'This Month',
                'all_time' => 'All Time',
            ] as $value => $label)
                <button
                    type="button"
                    wire:click="setPeriod('{{ $value }}')"
                    role="tab"
                    aria-selected="{{ $period === $value ? 'true' : 'false' }}"
                    class="min-w-0 flex-1 rounded-xl px-2 py-3 text-[11px] font-black uppercase tracking-wide transition sm:text-sm {{ $period === $value ? 'bg-gradient-to-r from-purple-600 to-blue-600 text-white shadow-lg shadow-purple-950/40' : 'text-slate-400 hover:bg-white/5 hover:text-white' }}"
                >
                    {{ $label }}
                </button>
            @endforeach
        </div>

        <div class="mx-auto mt-6 max-w-4xl overflow-hidden rounded-3xl border border-purple-500/25 bg-slate-900/65 shadow-2xl shadow-purple-950/20 backdrop-blur-xl">
            <div class="hidden grid-cols-[90px_minmax(0,1fr)_160px] border-b border-slate-700/80 bg-slate-950/60 px-6 py-4 text-xs font-black uppercase tracking-[0.16em] text-slate-400 sm:grid">
                <span>Rank</span>
                <span>Player Name</span>
                <span class="text-right">Wins</span>
            </div>

            <div wire:loading.flex wire:target="setPeriod" class="min-h-40 items-center justify-center text-sm font-bold text-purple-300">
                Loading winners...
            </div>

            <div wire:loading.remove wire:target="setPeriod">
                @forelse($rankings as $row)
                    @php
                        $rankClass = match($row['rank']) {
                            1 => 'border-amber-300/40 bg-amber-400/10 text-amber-200',
                            2 => 'border-slate-300/40 bg-slate-300/10 text-slate-200',
                            3 => 'border-orange-400/40 bg-orange-500/10 text-orange-200',
                            default => 'border-purple-400/25 bg-purple-500/10 text-purple-200',
                        };
                    @endphp
                    <article data-bb-reveal wire:key="top-winner-{{ $period }}-{{ $row['rank'] }}" style="--bb-reveal-delay: {{ min($loop->index, 5) * 70 }}ms" class="grid grid-cols-[58px_minmax(0,1fr)] items-center gap-x-3 border-b border-slate-800/80 px-4 py-4 last:border-b-0 sm:grid-cols-[90px_minmax(0,1fr)_160px] sm:px-6">
                        <span class="row-span-2 inline-flex h-10 w-10 items-center justify-center rounded-full border text-sm font-black sm:row-span-1 {{ $rankClass }}">#{{ $row['rank'] }}</span>
                        <span class="min-w-0 truncate font-bold text-white">{{ $row['player_name'] }}</span>
                        <span class="mt-1 text-sm font-black text-emerald-300 sm:mt-0 sm:text-right sm:text-base">${{ $row['wins'] }}</span>
                    </article>
                @empty
                    <p data-bb-reveal class="px-6 py-12 text-center text-sm text-slate-400">No winners are available for this period yet.</p>
                @endforelse
            </div>
        </div>
    </div>
</section>
