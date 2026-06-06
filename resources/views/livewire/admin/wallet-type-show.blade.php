<div>

    <!-- HEADER -->
    <div class="flex justify-between items-center mb-6">

        <div>
            <h1 class="text-2xl font-bold text-white">
                {{ $type->agent->name }}
                <span class="text-slate-500">→</span>
                {{ $type->name }}
            </h1>

            <p class="text-slate-400 text-sm">
                Wallet Type Management
            </p>
        </div>
        @if(auth()->user()->hasRole('admin'))
            <a href="{{ url('/admin/accounts/agent-'.$type->wallet_agent_id) }}"
               class="px-4 py-2 bg-slate-800 rounded-xl">
                ← Back
            </a>
        @elseif(auth()->user()->hasRole('agent'))
            <a href="{{ url('/agent/accounts/agent-'.$type->wallet_agent_id) }}"
               class="px-4 py-2 bg-slate-800 rounded-xl">
                ← Back
            </a>
        @endif


    </div>

    <!-- UPDATE TYPE -->
    <div class="p-5 mb-6 bg-slate-900 border border-slate-800 rounded-2xl">

        <h2 class="text-lg font-bold mb-4">Edit Wallet Type</h2>
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
                placeholder="Wallet Type Name"
            />

            <button
                wire:click="updateType"
                wire:loading.attr="disabled"
                wire:target="updateType"
                class="px-4 py-2 bg-purple-600 rounded-xl"
            >
                <span wire:loading.remove wire:target="updateType">
                    Save
                </span>

                <span wire:loading wire:target="updateType">
                    Saving...
                </span>
            </button>

        </div>

    </div>

    <!-- ADD WALLET BUTTON -->
    <div class="flex justify-between items-center mb-4">

        <h2 class="text-lg font-bold text-white">
            Wallets
        </h2>

        <button
            wire:click="$set('showWalletModal', true)"
            class="px-4 py-2 bg-purple-600 rounded-xl"
        >
            + Add Wallet
        </button>

    </div>

    <!-- WALLET GRID -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

        @foreach($this->wallets as $wallet)
            @if(auth()->user()->hasRole('admin'))
                <a href="{{ route('admin.wallets.wallet', [
        'agent' => $type->wallet_agent_id,
        'type' => $type->id,
        'wallet' => $wallet->id,
    ]) }}"
                   class="p-4 bg-slate-900 border border-slate-800 rounded-2xl hover:border-purple-500">

                    <h3 class="text-white font-bold">
                        {{ $wallet->name }}
                    </h3>

                    <p class="text-sm text-slate-400 mt-1">
                        {{ $wallet->account_identifier }}
                    </p>

                    @if($wallet->qr_image)
                        <img
                            src="{{ asset('storage/'.$wallet->qr_image) }}"
                            class="h-20 mt-3 rounded-lg object-cover"
                        >
                    @endif
                    <div class="mt-3 text-xs text-slate-400">

                        <div>
                            Created By:
                            {{ $wallet->creator?->name ?? '-' }}
                        </div>

                        <div>
                            Updated By:
                            {{ $wallet->updater?->name ?? '-' }}
                        </div>

                    </div>
                    <div class="mt-4 flex items-center justify-between">

    <span class="text-sm">
        {{ $wallet->is_active ? 'Active' : 'Disabled' }}
    </span>

                        <button
                            wire:click.prevent="toggleWallet({{ $wallet->id }})"
                            class="px-3 py-1 rounded-lg
        {{ $wallet->is_active
            ? 'bg-green-600'
            : 'bg-red-600' }}"
                        >
                            {{ $wallet->is_active ? 'ON' : 'OFF' }}
                        </button>

                    </div>
                </a>
            @endif

            @if(auth()->user()->hasRole('agent'))
                    <a href="{{ route('agent.wallets.wallet', [
        'agent' => $type->wallet_agent_id,
        'type' => $type->id,
        'wallet' => $wallet->id,
    ]) }}"
                       class="p-4 bg-slate-900 border border-slate-800 rounded-2xl hover:border-purple-500">

                        <h3 class="text-white font-bold">
                            {{ $wallet->name }}
                        </h3>

                        <p class="text-sm text-slate-400 mt-1">
                            {{ $wallet->account_identifier }}
                        </p>

                        @if($wallet->qr_image)
                            <img
                                src="{{ asset('storage/'.$wallet->qr_image) }}"
                                class="h-20 mt-3 rounded-lg object-cover"
                            >
                        @endif

                    </a>
            @endif


        @endforeach

    </div>

    <!-- ADD WALLET MODAL -->
    @if($showWalletModal)

        <div class="fixed inset-0 bg-black/70 flex items-center justify-center z-[999]">

            <div class="w-full max-w-lg h-[70vh] flex flex-col m-auto rounded-2xl bg-slate-900 border border-slate-700 overflow-hidden">

                <div class="p-5 border-b border-slate-800 flex justify-between items-center">
                    <h2 class="text-white font-bold">Add Wallet</h2>

                    <button wire:click="$set('showWalletModal', false)">✕</button>
                </div>

                <div class="flex-1 overflow-y-auto p-5 min-h-0 custom-scrollbar space-y-4">

                    <input
                        wire:model="wallet_name"
                        placeholder="Wallet Name (e.g. John Cena)"
                        class="w-full bg-slate-800 rounded-xl p-2 text-white"
                    />

                    <input
                        wire:model="account_identifier"
                        placeholder="Cashtag / Email / Phone"
                        class="w-full bg-slate-800 rounded-xl p-2 text-white"
                    />

                    <div>

                        <label class="text-sm text-slate-400">
                            Wallet QR Image
                        </label>

                        <div class="mt-2">

                            <label
                                for="qr_image"
                                class="group flex cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed border-slate-600 bg-slate-800/40 p-6 transition hover:border-indigo-500 hover:bg-slate-800"
                            >

                                @if($qr_image)

                                    <img
                                        src="{{ $qr_image->temporaryUrl() }}"
                                        class="max-h-52 rounded-xl object-cover border-2 border-indigo-500"
                                    >

                                    <span class="mt-3 text-indigo-300">
        Click to change QR image
    </span>

                                @else

                                    <svg
                                        xmlns="http://www.w3.org/2000/svg"
                                        class="h-12 w-12 text-slate-400 group-hover:text-indigo-400"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"
                                        />
                                    </svg>

                                    <span class="mt-4 text-slate-300">
                    Upload Wallet QR Image
                </span>

                                    <span class="text-xs text-slate-500 mt-1">
                    Click to browse
                </span>

                                @endif

                                <input
                                    id="qr_image"
                                    type="file"
                                    wire:model="qr_image"
                                    class="hidden"
                                    accept="image/*"
                                >

                            </label>

                            <div
                                wire:loading
                                wire:target="qr_image"
                                class="mt-3 text-sm text-indigo-400"
                            >
                                Uploading image...
                            </div>

                            @error('qr_image')
                            <p class="mt-2 text-sm text-red-400">
                                {{ $message }}
                            </p>
                            @enderror

                        </div>

                    </div>

                </div>

                <div class="p-5 flex justify-end gap-3 border-t border-slate-800">

                    <button
                        wire:click="$set('showWalletModal', false)"
                        class="px-4 py-2 bg-gray-700 rounded-xl"
                    >
                        Cancel
                    </button>

                    <button
                        wire:click="createWallet"
                        wire:loading.attr="disabled"
                        wire:target="createWallet"
                        class="px-4 py-2 bg-purple-600 rounded-xl"
                    >
                        <span wire:loading.remove wire:target="createWallet">
                            Save
                        </span>

                        <span wire:loading wire:target="createWallet">
                            Saving...
                        </span>
                    </button>

                </div>

            </div>

        </div>

    @endif

</div>
