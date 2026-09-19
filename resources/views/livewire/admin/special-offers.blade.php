<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-white">Special Offers</h1>
            <p class="mt-1 text-sm text-slate-400">Manage up to four promotions shown to players.</p>
        </div>

        @if($offers->count() < \App\Models\SpecialOffer::MAX_OFFERS)
            <button type="button" wire:click="create" class="rounded-xl bg-gradient-to-r from-purple-600 to-indigo-600 px-5 py-3 font-black text-white transition hover:brightness-110">
                Add Offer
            </button>
        @else
            <p class="rounded-xl border border-amber-400/30 bg-amber-400/10 px-4 py-3 text-sm font-bold text-amber-200">Maximum 4 offers reached.</p>
        @endif
    </div>

    @error('offerLimit')
        <p class="rounded-xl border border-red-400/30 bg-red-500/10 p-3 text-sm text-red-200">{{ $message }}</p>
    @enderror

    @if(session()->has('success'))
        <div class="rounded-xl border border-emerald-400/30 bg-emerald-500/10 p-3 text-emerald-200">{{ session('success') }}</div>
    @endif

    <div class="grid gap-4 xl:grid-cols-2">
        @forelse($offers as $offer)
            @php
                $status = ! $offer->is_active
                    ? ['DISABLED', 'text-slate-300 border-slate-500/40 bg-slate-500/10']
                    : (today()->lt($offer->starts_at)
                        ? ['SCHEDULED', 'text-blue-200 border-blue-400/40 bg-blue-500/10']
                        : (today()->gt($offer->ends_at)
                            ? ['EXPIRED', 'text-rose-200 border-rose-400/40 bg-rose-500/10']
                            : ['ACTIVE NOW', 'text-emerald-200 border-emerald-400/40 bg-emerald-500/10']));
            @endphp
            <article wire:key="special-offer-{{ $offer->id }}" class="rounded-2xl border border-purple-500/20 bg-slate-900 p-5 shadow-xl shadow-purple-950/10">
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <span class="inline-flex rounded-full border px-3 py-1 text-[11px] font-black tracking-wider {{ $status[1] }}">{{ $status[0] }}</span>
                        <h2 class="mt-3 text-xl font-black text-white">{{ $offer->name }}</h2>
                    </div>
                    <div class="flex gap-2">
                        <button type="button" wire:click="edit({{ $offer->id }})" class="rounded-lg bg-purple-600/20 px-3 py-2 text-sm font-bold text-purple-200 hover:bg-purple-600/35">Edit</button>
                        <button type="button" wire:click="delete({{ $offer->id }})" wire:confirm="Delete this offer?" class="rounded-lg bg-rose-600/15 px-3 py-2 text-sm font-bold text-rose-200 hover:bg-rose-600/30">Delete</button>
                    </div>
                </div>
                <p class="mt-3 whitespace-pre-line text-sm leading-6 text-slate-300">{{ $offer->description }}</p>
                <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-slate-800 pt-4">
                    <p class="text-xs font-bold text-amber-200">{{ $offer->starts_at->format('M j, Y') }} – {{ $offer->ends_at->format('M j, Y') }}</p>
                    <button type="button" wire:click="toggle({{ $offer->id }})" class="rounded-lg border border-slate-700 px-3 py-2 text-xs font-black text-white hover:bg-slate-800">
                        {{ $offer->is_active ? 'Disable' : 'Enable' }}
                    </button>
                </div>
            </article>
        @empty
            <div class="col-span-full rounded-2xl border border-dashed border-slate-700 bg-slate-900/60 p-10 text-center text-slate-400">No special offers configured.</div>
        @endforelse
    </div>

    @if($showForm)
        <div class="bb-admin-modal-overlay fixed inset-0 z-[9999] flex items-center justify-center p-4">
            <form wire:submit="save" class="bb-admin-modal-shell max-w-2xl">
                <div class="bb-admin-modal-header flex items-center justify-between border-b border-slate-800 p-5">
                    <div>
                        <p class="text-xs font-black uppercase tracking-[.25em] text-amber-300">Promotion</p>
                        <h2 class="mt-1 text-xl font-black text-white">{{ $editingId ? 'Edit Offer' : 'New Offer' }}</h2>
                    </div>
                    <button type="button" wire:click="closeForm" class="bb-admin-modal-close" aria-label="Close offer form">&times;</button>
                </div>
                <div class="bb-admin-modal-body space-y-4 p-5">
                    <label class="block">
                        <span class="text-sm font-bold text-slate-300">Name</span>
                        <input wire:model="name" maxlength="120" class="mt-1 w-full rounded-xl border-slate-700 bg-slate-800 text-white">
                        @error('name')<span class="mt-1 block text-sm text-red-400">{{ $message }}</span>@enderror
                    </label>
                    <label class="block">
                        <span class="text-sm font-bold text-slate-300">Description</span>
                        <textarea wire:model="description" maxlength="2000" rows="5" class="mt-1 w-full rounded-xl border-slate-700 bg-slate-800 text-white"></textarea>
                        @error('description')<span class="mt-1 block text-sm text-red-400">{{ $message }}</span>@enderror
                    </label>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="block">
                            <span class="text-sm font-bold text-slate-300">From</span>
                            <input wire:model="starts_at" type="date" class="mt-1 w-full rounded-xl border-slate-700 bg-slate-800 text-white">
                            @error('starts_at')<span class="mt-1 block text-sm text-red-400">{{ $message }}</span>@enderror
                        </label>
                        <label class="block">
                            <span class="text-sm font-bold text-slate-300">To</span>
                            <input wire:model="ends_at" type="date" class="mt-1 w-full rounded-xl border-slate-700 bg-slate-800 text-white">
                            @error('ends_at')<span class="mt-1 block text-sm text-red-400">{{ $message }}</span>@enderror
                        </label>
                    </div>
                    <label class="flex items-center gap-3 rounded-xl border border-slate-700 bg-slate-800/70 p-4">
                        <input wire:model="is_active" type="checkbox" class="rounded border-slate-600 bg-slate-900 text-purple-600 focus:ring-purple-500">
                        <span class="font-bold text-white">Enabled for player visibility during its date range</span>
                    </label>
                </div>
                <div class="bb-admin-modal-footer flex justify-end gap-3 border-t border-slate-800 p-5">
                    <button type="button" wire:click="closeForm" class="bb-admin-modal-secondary rounded-xl px-4 py-2">Cancel</button>
                    <button type="submit" wire:loading.attr="disabled" wire:target="save" class="bb-admin-modal-primary rounded-xl px-5 py-2">
                        <span wire:loading.remove wire:target="save">Save Offer</span>
                        <span wire:loading wire:target="save">Saving...</span>
                    </button>
                </div>
            </form>
        </div>
    @endif
</div>
