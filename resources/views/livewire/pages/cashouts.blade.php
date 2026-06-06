<div class="min-h-screen bg-slate-950 text-white">

    <div class="border-b border-slate-800">
        <div class="mx-auto max-w-7xl px-6 py-14">
            <h1 class="text-4xl font-black text-white">
                Request Withdrawal
            </h1>

            <p class="mt-3 text-slate-400">
                Submit a withdrawal request for your gaming account.
            </p>
        </div>
    </div>

    <div class="mx-auto max-w-xl px-6 py-12">

        @if($cashoutSubmitted)

            <div class="rounded-3xl border border-green-500/30 bg-green-500/10 p-8">

                <h2 class="text-2xl font-bold text-green-300">
                    Cashout Request Submitted
                </h2>

                <p class="mt-4 text-slate-300">
                    Reference Number:
                </p>

                <p class="mt-2 text-xl font-black text-green-400">
                    {{ $cashoutReference }}
                </p>

                <p class="mt-6 text-slate-400">
                    Your request has been submitted successfully.
                </p>

                <p class="mt-2 text-slate-400">
                    Please wait while our team verifies your cashout request.
                </p>

            </div>

        @else

            <div class="rounded-3xl border border-slate-800 bg-slate-900 p-8">

                <form wire:submit="submit" class="space-y-6">

                    {{-- GAME --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-300">
                            Game
                        </label>

                        <select
                            wire:model.live="game_id"
                            class="mt-2 w-full rounded-xl border border-slate-700 bg-slate-800 text-white"
                        >
                            <option value="">Select Game</option>

                            @foreach($games as $game)
                                <option value="{{ $game->id }}">
                                    {{ $game->name }}
                                </option>
                            @endforeach

                        </select>

                        @error('game_id')
                        <p class="mt-2 text-sm text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- GAME USERNAME --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-300">
                            Game Username
                        </label>

                        <select
                            wire:model="game_account_id"
                            class="mt-2 w-full rounded-xl border border-slate-700 bg-slate-800 text-white"
                        >
                            <option value="">Select Username</option>

                            @foreach($this->playerAccounts as $account)

                                <option value="{{ $account->id }}">
                                    {{ $account->game_username }}
                                </option>

                            @endforeach

                        </select>

                        @error('game_account_id')
                        <p class="mt-2 text-sm text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- AMOUNT --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-300">
                            Cashout Amount
                        </label>

                        <input
                            type="number"
                            step="0.01"
                            wire:model="amount"
                            placeholder="Enter amount"
                            class="mt-2 w-full rounded-xl border border-slate-700 bg-slate-800 text-white"
                        >

                        @error('amount')
                        <p class="mt-2 text-sm text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- WALLET TYPE --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-300">
                            Wallet Type
                        </label>

                        <select
                            wire:model="wallet_type"
                            class="mt-2 w-full rounded-xl border border-slate-700 bg-slate-800 text-white"
                        >
                            <option value="">
                                Select Wallet Type
                            </option>

                            @foreach($walletTypes as $type)

                                <option value="{{ $type }}">
                                    {{ ucfirst($type) }}
                                </option>

                            @endforeach

                        </select>

                        @error('wallet_type')
                        <p class="mt-2 text-sm text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- WALLET ADDRESS --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-300">
                            Wallet Address / Cashtag
                        </label>

                        <input
                            type="text"
                            wire:model="wallet_address"
                            placeholder="Example: $john123"
                            class="mt-2 w-full rounded-xl border border-slate-700 bg-slate-800 text-white"
                        >

                        @error('wallet_address')
                        <p class="mt-2 text-sm text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- OR --}}
                    <div class="flex items-center gap-4">

                        <div class="h-px flex-1 bg-slate-700"></div>

                        <span class="text-sm text-slate-400">
                            OR
                        </span>

                        <div class="h-px flex-1 bg-slate-700"></div>

                    </div>

                    {{-- QR UPLOAD --}}
                    <div>

                        <label class="block text-sm font-medium text-slate-300 mb-2">
                            Upload Wallet QR Code
                        </label>

                        <label
                            for="qr_image"
                            class="group flex cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed border-slate-600 bg-slate-800/40 p-6 transition hover:border-indigo-500"
                        >

                            @if($qr_image)

                                <img
                                    src="{{ $qr_image->temporaryUrl() }}"
                                    class="max-h-52 rounded-xl border border-indigo-500"
                                >

                                <span class="mt-3 text-indigo-300">
                                    Change QR Code
                                </span>

                            @else

                                <span class="text-slate-300">
                                    Upload QR Image
                                </span>

                            @endif

                            <input
                                id="qr_image"
                                type="file"
                                wire:model="qr_image"
                                class="hidden"
                                accept="image/*"
                            />

                        </label>

                        @error('qr_image')
                        <p class="mt-2 text-sm text-red-400">{{ $message }}</p>
                        @enderror

                        <div
                            wire:loading
                            wire:target="qr_image"
                            class="mt-2 text-sm text-indigo-400"
                        >
                            Uploading...
                        </div>

                    </div>

                    {{-- SUBMIT --}}
                    <button
                        type="submit"
                        wire:loading.attr="disabled"
                        wire:target="submit"
                        class="w-full rounded-xl bg-gradient-to-r from-purple-600 to-indigo-600 py-3 font-bold text-white"
                    >

                        <span
                            wire:loading.remove
                            wire:target="submit"
                        >
                            Submit Cashout Request
                        </span>

                        <span
                            wire:loading
                            wire:target="submit"
                        >
                            Submitting...
                        </span>

                    </button>

                </form>

            </div>

        @endif

    </div>


</div>
