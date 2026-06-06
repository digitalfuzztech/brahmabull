<div class="min-h-screen bg-slate-950 text-white">


    {{-- HERO SECTION --}}
    <div class="border-b border-slate-800">
        <div class="mx-auto max-w-7xl px-6 py-14">
            <h1 class="text-4xl font-black text-white">
                All Games
            </h1>
            <p class="mt-3 text-slate-400">
                Browse all available games at BrahmaBull Gaming Club.
            </p>
        </div>
    </div>


    <div class="mx-auto max-w-7xl px-6 py-12">

        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-6">

            @foreach($games as $game)

                <div class="group overflow-hidden rounded-3xl border border-slate-800 bg-slate-900 transition hover:-translate-y-2 hover:border-purple-500">

                    <div class="aspect-[4/5] overflow-hidden">
                        <img
                            src="{{ asset('storage/' . $game->image) }}"
                            class="h-full w-full object-cover transition duration-500 group-hover:scale-110"
                            alt="{{ $game->name }}"
                        >
                    </div>

                    <div class="p-4">
                        <h3 class="font-bold text-white">
                            {{ $game['name'] }}
                        </h3>

                        <p class="mt-1 text-xs text-purple-400">
                            {{ $game['provider'] }}
                        </p>

                        <button
                            wire:click="openPlayModal({{ $game->id }})"
                            class="mt-4 w-full rounded-xl bg-gradient-to-r from-purple-600 to-indigo-600 py-2 text-sm font-bold"
                        >
                            Play
                        </button>
                    </div>

                </div>

            @endforeach

        </div>

    </div>

    @if($showModal)
        <div class="fixed inset-0 z-[999] flex justify-center bg-black/70 backdrop-blur-sm">

            <div class="w-full max-w-md h-[70vh] flex flex-col m-auto rounded-2xl px-2 bg-slate-900 border border-slate-700 overflow-hidden">

                <!-- Header -->
                <div class="flex justify-between items-center p-6 border-b mb-2 border-slate-800 z-[999]">

                    <h2 class="text-xl font-bold text-white">
                        Play {{ $selectedGame->name }}
                    </h2>

                    <button wire:click="closeModal" class="text-white text-xl">✕</button>

                </div>
                @if($depositSubmitted)

                    <div
                        class="mb-6 mx-2 rounded-2xl border border-green-500/30 bg-green-500/10 p-5 text-green-300"
                    >

                        <h3 class="font-bold text-lg">
                            Deposit Submitted Successfully </br> [ {{ $depositReference }}]
                        </h3>

                        <p class="mt-2">
                            Your payment proof has been submitted.
                        </p>

                        <p class="mt-2">
                            Please wait a few minutes while our agent verifies your payment.
                        </p>
                        <p class="mt-2">
                            Also please keep the Deposit Reference Number safe in case you need support.
                        </p>

                    </div>

                @endif
                <!-- BODY (SCROLL AREA) -->
                <div class="flex-1 overflow-y-auto p-6 space-y-6 min-h-0 custom-scrollbar">
                <!-- GAME -->
                <div class="mb-4">
                    <label class="text-sm text-slate-400">Game</label>

                    <input
                        type="text"
                        disabled
                        value="{{ $selectedGame->name }}"
                        class="w-full mt-1 rounded-xl bg-slate-800 border-slate-700 text-white"
                    >
                </div>

                <!-- AMOUNT -->
                <div class="mb-4">
                    <label class="text-sm text-slate-400">Deposit Amount</label>

                    <input type="number"
                           wire:model="amount"
                           class="w-full mt-1 rounded-xl bg-slate-800 border-slate-700 text-white"
                           placeholder="Enter amount"
                    />
                </div>

                <!-- WALLET TYPE -->
                <div class="mb-4">
                    <label class="text-sm text-slate-400">Payment Method</label>

                    <select wire:model.live="paymentType"
                            class="w-full mt-1 rounded-xl bg-slate-800 border-slate-700 text-white">

                        <option value="" class="text-gray-500">Select Payment Type</option>

                        @foreach($walletTypes as $type)
                            <option value="{{ $type }}">
                                {{ ucfirst($type) }}
                            </option>
                        @endforeach

                    </select>
                </div>

                <!-- NEW: WALLET DETAILS (QR / TAGS) -->
                @if($paymentType)

                    <div class="mt-4 space-y-3">
                        <label class="text-sm text-slate-400">Available Wallets</label>
                        @foreach($this->filteredWallets as $wallet)

                            <div
                                wire:key="wallet-{{ $wallet->id }}"
                                wire:click="selectWallet({{ $wallet->id }})"
                                class="p-3 rounded-xl cursor-pointer border transition
        {{ $selectedWallet == $wallet->id
            ? 'border-purple-500 bg-slate-800'
            : 'border-slate-700 bg-slate-900'
        }}"
                            >

                                <div class="flex items-center justify-between p-4">

                                    <div>
                                        <p class="font-bold text-white">
                                            {{ $wallet->name }}
                                        </p>

                                        <p class="text-sm text-slate-400">
                                            {{ $wallet->account_identifier }}
                                        </p>
                                    </div>

                                    @if($selectedWallet == $wallet->id)
                                        <span class="text-green-400 font-bold">
                    ✓ Selected
                </span>
                                    @endif

                                </div>

                            </div>

                        @endforeach

                    </div>

                @endif
                @if($selectedWallet)

                    <div class="mt-3">

                        <button
                            wire:click="$set('showWalletPreview', true)"
                            type="button"
                            class="text-purple-400 hover:text-purple-300 text-sm font-semibold"
                        >
                            Preview Selected Wallet for QR-Code
                        </button>

                    </div>

                @endif
                <!-- SCREENSHOT -->
                <div class="mb-4">
                    <label class="text-sm text-slate-400">Payment Screenshot</label>

                    <div class="mb-4">
                        <label
                            for="proofImage"
                            class="group mt-2 flex cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed border-slate-600 bg-slate-800/40 p-6 transition hover:border-indigo-500 hover:bg-slate-800"
                        >

                            @if($proofImage)

                                <img
                                    src="{{ $proofImage->temporaryUrl() }}"
                                    class="max-h-52 rounded-xl object-cover border-2 border-indigo-500"
                                >

                                <span class="mt-3 text-indigo-300">
                Click to change screenshot
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
                Upload Payment Screenshot
            </span>

                                <span class="text-xs text-slate-500 mt-1">
                Click to browse
            </span>

                            @endif

                            <input
                                id="proofImage"
                                type="file"
                                wire:model="proofImage"
                                class="hidden"
                                accept="image/*"
                            />

                        </label>

                        <div
                            wire:loading
                            wire:target="proofImage"
                            class="mt-3 text-sm text-indigo-400"
                        >
                            Uploading image...
                        </div>

                    </div>
                </div>


                </div>
                <!-- SUBMIT -->
                <div class="p-6 border-t border-slate-800">
                <button
                    wire:click="submitDeposit"
                    wire:loading.attr="disabled"
                    wire:target="submitDeposit"
                    class="w-full rounded-xl bg-gradient-to-r from-purple-600 to-indigo-600 py-3 font-bold text-white"
                >

    <span
        wire:loading.remove
        wire:target="submitDeposit"
    >
        Submit Deposit
    </span>

                    <span
                        wire:loading
                        wire:target="submitDeposit"
                    >
        Submitting...
    </span>

                </button>
                </div>
            </div>

        </div>
    @endif

    @if($showWalletPreview && $this->selectedWalletModel)

        <div class="fixed inset-0 z-[1001] flex items-center justify-center bg-black/80">

            <div class="w-full max-w-md rounded-2xl bg-slate-900 border border-slate-700 p-6">

                <div class="flex items-center justify-between mb-8">

                    <h3 class="text-xl font-bold">
                        Wallet Details
                    </h3>

                    <button
                        wire:click="$set('showWalletPreview', false)"
                        class="text-xl"
                    >
                        ✕
                    </button>

                </div>

                <div class="space-y-4">

                    <div class="flex items-center gap-3">
                        <p class="text-slate-400 text-sm">
                            Wallet Owner:
                        </p>

                        <p class="font-bold text-white">
                            {{ $this->selectedWalletModel->name }}
                        </p>
                    </div>

                    <div class="flex items-center gap-3">
                        <p class="text-slate-400 text-sm">
                            Payment Tag:
                        </p>

                        <p class="font-bold text-purple-400 break-all">
                            {{ $this->selectedWalletModel->account_identifier }}
                        </p>
                    </div>

                    @if($this->selectedWalletModel->qr_image)

                        <div>

                            <p class="text-slate-400 text-sm mb-2">
                                QR Code:
                            </p>

                            <img
                                src="{{ asset('storage/' . $this->selectedWalletModel->qr_image) }}"
                                class="w-64 mx-auto rounded-xl border border-slate-700"
                            >

                        </div>

                    @endif

                </div>

            </div>

        </div>

    @endif


</div>
