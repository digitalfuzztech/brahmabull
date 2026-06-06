<div>

    <!-- HEADER -->
    <div class="flex justify-between items-center mb-6">

        <div>
            <h1 class="text-2xl font-bold text-white">
                {{ $agent->name }}
            </h1>

            <p class="text-slate-400 text-sm">
                Wallet Agent Management
            </p>
        </div>
        @if(auth()->user()->hasRole('admin'))
        <a href="{{ route('admin.wallets') }}"
           class="px-4 py-2 bg-slate-800 rounded-xl">
            ← Back
        </a>
            @elseif(auth()->user()->hasRole('agent'))
            <a href="{{ route('agent.wallets') }}"
               class="px-4 py-2 bg-slate-800 rounded-xl">
                ← Back
            </a>
        @endif
    </div>

    <!-- UPDATE AGENT -->
    <div class="p-5 mb-6 bg-slate-900 border border-slate-800 rounded-2xl">

        <h2 class="text-lg font-bold mb-4">Update Agent</h2>
        @if(session()->has('success'))

            <div
                x-data="{ show: true }"
                x-init="setTimeout(() => show = false, 5000)"
                x-show="show"
                x-transition
                class="mb-6 rounded-2xl border border-green-500/30 bg-green-500/10 p-4 text-green-300"
            >
                {{ session('success') }}
            </div>

        @endif
        <div class="flex gap-3">

            <input
                wire:model="name"
                class="w-full bg-slate-800 p-2 rounded-xl text-white"
                placeholder="Agent Name"
            />

            <button
                wire:click="updateAgent"
                wire:loading.attr="disabled"
                wire:target="updateAgent"
                class="px-4 py-2 bg-purple-600 rounded-xl"
            >
                <span wire:loading.remove wire:target="updateAgent">
                    Save
                </span>

                <span wire:loading wire:target="updateAgent">
                    Saving...
                </span>
            </button>

        </div>

    </div>

    <!-- ADD TYPE BUTTON -->
    <div class="flex justify-between items-center mb-4">

        <h2 class="text-lg font-bold text-white">
            Wallet Types
        </h2>

        <button
            wire:click="$set('showTypeModal', true)"
            class="px-4 py-2 bg-purple-600 rounded-xl"
        >
            + Add Wallet Type
        </button>

    </div>

    <!-- TYPES GRID -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

        @foreach($this->types as $type)
            @if(auth()->user()->hasRole('admin'))
                <a href="{{ url('/admin/wallets/agent-'.$agent->id.'/wallet-type-'.$type->id) }}"
                   class="p-4 bg-slate-900 border border-slate-800 rounded-2xl hover:border-purple-500">

                    <h3 class="text-white font-bold">
                        {{ $type->name }}
                    </h3>

                    <p class="text-sm text-slate-400 mt-2">
                        Wallets: {{ $type->wallets()->count() }}
                    </p>

                </a>
            @endif

            @if(auth()->user()->hasRole('agent'))
                    <a href="{{ url('/agent/wallets/agent-'.$agent->id.'/wallet-type-'.$type->id) }}"
                       class="p-4 bg-slate-900 border border-slate-800 rounded-2xl hover:border-purple-500">

                        <h3 class="text-white font-bold">
                            {{ $type->name }}
                        </h3>

                        <p class="text-sm text-slate-400 mt-2">
                            Wallets: {{ $type->wallets()->count() }}
                        </p>

                    </a>
            @endif


        @endforeach

    </div>

    <!-- ADD TYPE MODAL -->
    @if($showTypeModal)

        <div class="fixed inset-0 bg-black/70 flex items-center justify-center z-[999]">

            <div class="w-full max-w-md bg-slate-900 rounded-2xl border border-slate-700">

                <div class="p-5 border-b border-slate-800 flex justify-between">
                    <h2 class="text-white font-bold">Add Wallet Type</h2>

                    <button wire:click="$set('showTypeModal', false)">✕</button>
                </div>

                <div class="p-5">

                    <input
                        wire:model="typeName"
                        placeholder="e.g. CashApp, PayPal"
                        class="w-full bg-slate-800 rounded-xl p-2 text-white"
                    />

                </div>

                <div class="p-5 flex justify-end gap-3 border-t border-slate-800">

                    <button
                        wire:click="$set('showTypeModal', false)"
                        class="px-4 py-2 bg-gray-700 rounded-xl"
                    >
                        Cancel
                    </button>

                    <button
                        wire:click="createType"
                        wire:loading.attr="disabled"
                        wire:target="createType"
                        class="px-4 py-2 bg-purple-600 rounded-xl"
                    >
                        <span wire:loading.remove wire:target="createType">
                            Save
                        </span>

                        <span wire:loading wire:target="createType">
                            Saving...
                        </span>
                    </button>

                </div>

            </div>

        </div>

    @endif

</div>
