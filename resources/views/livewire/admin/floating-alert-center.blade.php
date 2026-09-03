<div wire:poll.2s="pollAlerts" class="pointer-events-none fixed inset-x-3 bottom-3 z-[9998] flex flex-col items-end gap-3 sm:left-auto sm:right-5 sm:w-96">
    @foreach($this->visibleAlerts as $alert)
        <article wire:key="floating-alert-{{ $alert['key'] }}" x-data x-init="setTimeout(() => $wire.dismiss(@js($alert['key'])), 7000)" class="pointer-events-auto w-full overflow-hidden rounded-2xl border border-slate-700 bg-slate-900/95 shadow-2xl backdrop-blur-xl">
            <div class="flex items-start gap-3 p-4">
                <button type="button" wire:click="open(@js($alert['key']))" class="flex min-w-0 flex-1 items-start gap-3 text-left">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full {{ $alert['kind'] === 'team' ? 'bg-purple-500/20 text-purple-300' : ($alert['kind'] === 'support' ? 'bg-cyan-500/20 text-cyan-300' : 'bg-amber-500/20 text-amber-300') }}">
                        {{ $alert['kind'] === 'team' ? 'T' : ($alert['kind'] === 'support' ? 'S' : '!') }}
                    </span>
                    <span class="min-w-0 flex-1"><span class="block truncate font-black text-white">{{ $alert['title'] }}</span><span class="block text-xs font-bold text-slate-300">{{ $alert['subtitle'] }}</span><span class="mt-1 block truncate text-sm text-slate-400">{{ $alert['preview'] }}</span></span>
                </button>
                <button type="button" wire:click="dismiss(@js($alert['key']))" class="shrink-0 rounded-lg px-2 py-1 text-slate-400 hover:bg-slate-800 hover:text-white" aria-label="Dismiss alert">×</button>
            </div>
        </article>
    @endforeach
</div>
