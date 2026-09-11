<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-white">Player Rank Settings</h1>
        <p class="mt-1 text-sm text-slate-400">Control the Top Winners rankings shown on the public homepage.</p>
    </div>

    @if(session()->has('success'))
        <div x-data="{ show: true }" x-init="setTimeout(() => show = false, 5000)" x-show="show" class="rounded-xl bg-green-600 p-3 text-white">
            {{ session('success') }}
        </div>
    @endif

    <form wire:submit="save" class="space-y-6">
        <section class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
            <h2 class="text-lg font-black text-white">Ranking Mode</h2>
            <div class="mt-4 grid max-w-xl grid-cols-2 rounded-xl border border-slate-700 bg-slate-950 p-1">
                @foreach(['automatic' => 'Automatic', 'manual' => 'Manual'] as $value => $label)
                    <label class="cursor-pointer rounded-lg px-4 py-3 text-center text-sm font-bold transition {{ $mode === $value ? 'bg-purple-600 text-white' : 'text-slate-400 hover:text-white' }}">
                        <input type="radio" wire:model.live="mode" value="{{ $value }}" class="sr-only">
                        {{ $label }}
                    </label>
                @endforeach
            </div>
            @error('mode')<p class="mt-2 text-sm text-red-400">{{ $message }}</p>@enderror

            @if($mode === 'automatic')
                <p class="mt-4 max-w-3xl rounded-xl border border-blue-500/20 bg-blue-500/10 p-4 text-sm leading-6 text-blue-100">
                    Rankings are calculated from each player's total successfully paid Cashout amounts. Pending, approved, and rejected requests are excluded.
                </p>
            @endif
        </section>

        @if($mode === 'manual')
            <section class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
                <div class="flex flex-wrap gap-2">
                    @foreach([
                        'last_7_days' => 'Last 7 Days',
                        'this_month' => 'This Month',
                        'all_time' => 'All Time',
                    ] as $value => $label)
                        <button type="button" wire:click="setPeriod('{{ $value }}')" class="rounded-full px-4 py-2 text-sm font-bold {{ $period === $value ? 'bg-amber-400 text-slate-950' : 'bg-slate-800 text-slate-300' }}">
                            {{ $label }}
                        </button>
                    @endforeach
                </div>

                <div class="mt-5 space-y-3">
                    @foreach($manualRows[$period] as $index => $row)
                        <div wire:key="manual-rank-{{ $period }}-{{ $index }}" class="grid gap-3 rounded-xl border border-slate-800 bg-slate-950/70 p-4 md:grid-cols-[80px_minmax(0,1fr)_220px] md:items-start">
                            <div class="pt-2 text-sm font-black text-amber-300">Rank #{{ $index + 1 }}</div>

                            <label class="block">
                                <span class="text-xs font-bold uppercase tracking-wide text-slate-400">Player Name</span>
                                <input wire:model="manualRows.{{ $period }}.{{ $index }}.player_name" type="text" maxlength="100" class="mt-1 w-full rounded-xl border-slate-700 bg-slate-800 text-white" placeholder="Leave blank if unused">
                                @error("manualRows.$period.$index.player_name")<span class="mt-1 block text-xs text-red-400">{{ $message }}</span>@enderror
                            </label>

                            <label class="block">
                                <span class="text-xs font-bold uppercase tracking-wide text-slate-400">Wins</span>
                                <input wire:model="manualRows.{{ $period }}.{{ $index }}.wins" type="number" min="0" max="99999999.99" step="0.01" class="mt-1 w-full rounded-xl border-slate-700 bg-slate-800 text-white" placeholder="0.00">
                                @error("manualRows.$period.$index.wins")<span class="mt-1 block text-xs text-red-400">{{ $message }}</span>@enderror
                            </label>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        <button type="submit" wire:loading.attr="disabled" wire:target="save" class="rounded-xl bg-gradient-to-r from-purple-600 to-blue-600 px-6 py-3 font-black text-white disabled:opacity-60">
            <span wire:loading.remove wire:target="save">Save Settings</span>
            <span wire:loading wire:target="save">Saving...</span>
        </button>
    </form>
</div>
