<div>
    @if($successMessage)
        <div
            x-data="{ show:true }"
            x-init="setTimeout(() => show = false, 5000)"
            x-show="show"
            class="mb-4 rounded-xl bg-green-500/10 border border-green-500/30 text-green-300 p-4"
        >
            {{ $successMessage }}
        </div>
    @endif
    <div class="mb-6 flex items-center justify-between">

        <div>
            <h1 class="text-2xl font-bold text-white">
                Agent: {{ $agent->name }}
            </h1>

            <p class="text-slate-400 text-sm">
                {{ $agent->email }}
            </p>
        </div>

        <div class="flex gap-3">

            <button
                wire:click="openEditModal"
                class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 rounded-xl"
            >
                Edit Agent
            </button>

            <a href="{{ route('admin.agents') }}"
               class="px-4 py-2 bg-slate-800 rounded-xl">
                ← Back
            </a>

        </div>

    </div>

    <div class="grid grid-cols-3 gap-4 mb-8">

        <div class="p-4 bg-slate-900 rounded-xl border border-slate-800">
            <p class="text-slate-400 text-sm">Deposits Verified</p>
            <p class="text-xl font-bold text-white">
                {{ $this->deposits->count() }}
            </p>
        </div>

        <div class="p-4 bg-slate-900 rounded-xl border border-slate-800">
            <p class="text-slate-400 text-sm">Cashouts Verified</p>
            <p class="text-xl font-bold text-white">
                {{ $this->cashouts->count() }}
            </p>
        </div>

        <div class="p-4 bg-slate-900 rounded-xl border border-slate-800">
            <p class="text-slate-400 text-sm">Wallets Created</p>
            <p class="text-xl font-bold text-white">
                {{ $this->wallets->count() }}
            </p>
        </div>

    </div>
    <div class="mb-10">

        <h2 class="text-lg font-bold mb-3">Deposits Handled</h2>

        <div class="space-y-2">

            @foreach($this->deposits as $deposit)

                <div class="p-3 bg-slate-900 border border-slate-800 rounded-xl flex justify-between">

                    <div>
                        <p class="text-white font-bold">
                            {{ $deposit->reference ?? 'No Ref' }}
                        </p>

                        <p class="text-xs text-slate-400">
                            Amount: {{ $deposit->amount }}
                        </p>
                    </div>

                    <span class="text-purple-400 text-sm">
                    {{ $deposit->status }}
                </span>

                </div>

            @endforeach

        </div>

    </div>
    <div class="mb-10">

        <h2 class="text-lg font-bold mb-3">Cashouts Verified</h2>

        <div class="space-y-2">

            @foreach($this->cashouts as $cashout)

                <div class="p-3 bg-slate-900 border border-slate-800 rounded-xl flex justify-between">

                    <div>
                        <p class="text-white font-bold">
                            {{ $cashout->reference }}
                        </p>

                        <p class="text-xs text-slate-400">
                            Amount: {{ $cashout->amount }}
                        </p>
                    </div>

                    <span class="text-green-400 text-sm">
                    {{ $cashout->status }}
                </span>

                </div>

            @endforeach

        </div>

    </div>
    <div class="mb-10">

        <h2 class="text-lg font-bold mb-3">Wallets Added / Edited</h2>

        <div class="space-y-2">

            @foreach($this->wallets as $wallet)

                <div class="p-3 bg-slate-900 border border-slate-800 rounded-xl">

                    <p class="text-white font-bold">
                        {{ $wallet->name ?? 'Wallet' }}
                    </p>

                    <p class="text-xs text-slate-400">
                        {{ $wallet->type ?? '' }}
                    </p>

                </div>

            @endforeach

        </div>

    </div>

    @if($showEditModal)
        <div class="fixed inset-0 bg-black/60 flex items-center justify-center z-50">

            <div class="bg-slate-900 w-full max-w-lg p-6 rounded-2xl border border-slate-800">

                <h2 class="text-xl font-bold mb-4">
                    Edit Agent
                </h2>

                <form wire:submit.prevent="updateAgent" class="space-y-4">

                    {{-- NAME --}}
                    <div>
                        <label class="text-sm text-slate-400">Name</label>
                        <input type="text"
                               wire:model="name"
                               class="w-full mt-1 bg-slate-800 border border-slate-700 rounded-xl p-2">
                    </div>

                    {{-- EMAIL --}}
                    <div>
                        <label class="text-sm text-slate-400">Email</label>
                        <input type="email"
                               wire:model="email"
                               class="w-full mt-1 bg-slate-800 border border-slate-700 rounded-xl p-2">
                    </div>

                    {{-- PASSWORD --}}
                    <div x-data="{ show:false }">
                        <label class="text-sm text-slate-400">Password (leave blank if unchanged)</label>

                        <div class="relative mt-1">

                            <input
                                type="password"
                                wire:model="password"
                                :type="show ? 'text' : 'password'"
                                class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2 pr-12"
                            />

                            <button
                                type="button"
                                @click="show = !show"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-indigo-400"
                            >

                                {{-- Eye --}}
                                <svg x-show="!show" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
                                     viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M15 12a3 3 0 11-6 0 3 3 0 016 0zm6 0c-1.73 3.89-6 7-11 7s-9.27-3.11-11-7c1.73-3.89 6-7 11-7s9.27 3.11 11 7z"/>
                                </svg>

                                {{-- Eye Off --}}
                                <svg x-show="show" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
                                     viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M13.875 18.825A10.05 10.05 0 0112 19c-5 0-9.27-3.11-11-7 1.02-2.29 2.76-4.2 4.96-5.42M9.88 9.88A3 3 0 0114.12 14.12M3 3l18 18"/>
                                </svg>

                            </button>

                        </div>
                    </div>

                    {{-- ACTIONS --}}
                    <div class="flex justify-end gap-2 pt-4">

                        <button type="button"
                                wire:click="closeEditModal"
                                class="px-4 py-2 bg-slate-700 rounded-xl">
                            Cancel
                        </button>

                        <button
                            type="submit"
                            wire:loading.attr="disabled"
                            wire:target="updateAgent"
                            class="w-full bg-indigo-600 hover:bg-indigo-700 py-2 rounded-xl font-semibold flex items-center justify-center gap-2"
                        >
                            <svg wire:loading wire:target="updateAgent" class="w-4 h-4 animate-spin" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                            </svg>

                            <span wire:loading.remove wire:target="updateAgent">
        Save Changes
    </span>

                            <span wire:loading wire:target="updateAgent">
        Updating...
    </span>
                        </button>

                    </div>

                </form>

            </div>

        </div>
    @endif
</div>
