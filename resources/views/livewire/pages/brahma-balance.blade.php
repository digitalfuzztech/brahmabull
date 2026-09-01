<div wire:poll.10s.visible="refreshBalance" class="relative">
    <div class="flex items-center gap-2 rounded-2xl border border-slate-700 bg-slate-900/80 px-3 py-2">
        <div class="leading-tight">
            <div class="text-[11px] uppercase text-slate-400">Brahma Balance</div>
            <div class="text-sm font-bold text-white">${{ number_format((float) $brahmaBalance, 2) }}</div>
        </div>

        <button
            type="button"
            wire:click="openModal"
            class="rounded-xl bg-gradient-to-r from-purple-600 to-indigo-600 px-3 py-2 text-xs font-bold text-white"
        >
            Deposit
        </button>
    </div>

    @teleport('body')
    <div>
    @if($showModal)
        <div class="fixed inset-0 z-[10000] overflow-y-auto bg-black/70 backdrop-blur-sm">
            <div class="flex min-h-full items-center justify-center p-4">
            <div class="w-full max-w-lg max-h-[calc(100vh-2rem)] flex flex-col overflow-hidden rounded-2xl border border-slate-700 bg-slate-900 text-white">
                <div class="flex items-center justify-between border-b border-slate-800 p-5">
                    <h2 class="text-lg font-bold">Brahma Balance Deposit</h2>
                    <button wire:click="closeModal" class="text-xl text-slate-300">x</button>
                </div>

                <div class="flex-1 overflow-y-auto p-5 space-y-4 custom-scrollbar">
                    @if($depositSubmitted)
                        <div class="rounded-2xl border border-green-500/30 bg-green-500/10 p-4 text-green-300">
                            <div class="font-bold">Deposit Submitted Successfully</div>
                            <div class="mt-1 text-sm">Reference: {{ $depositReference }}</div>
                        </div>
                    @endif

                    <div>
                        <label class="text-sm text-slate-400">Player Name</label>
                        <input type="text" disabled value="{{ auth()->user()->name }}" class="mt-1 w-full rounded-xl border-slate-700 bg-slate-800 text-white">
                    </div>

                    <div>
                        <label class="text-sm text-slate-400">Player Username</label>
                        <input type="text" disabled value="{{ auth()->user()->username }}" class="mt-1 w-full rounded-xl border-slate-700 bg-slate-800 text-white">
                    </div>

                    <div>
                        <label class="text-sm text-slate-400">Amount</label>
                        <input type="number" step="0.01" wire:model="amount" class="mt-1 w-full rounded-xl border-slate-700 bg-slate-800 text-white" placeholder="Enter amount">
                        @error('amount') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="text-sm text-slate-400">Payment Method</label>
                        <select wire:model.live="paymentType" class="mt-1 w-full rounded-xl border-slate-700 bg-slate-800 text-white">
                            <option value="">Select Payment Type</option>
                            @foreach($walletTypes as $type)
                                <option value="{{ $type }}">{{ ucfirst($type) }}</option>
                            @endforeach
                        </select>
                        @error('paymentType') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
                    </div>

                    @if($paymentType)
                        <div class="space-y-3">
                            <label class="text-sm text-slate-400">Available Wallets</label>
                            @foreach($this->filteredWallets as $wallet)
                                <div
                                    wire:key="brahma-wallet-{{ $wallet->id }}"
                                    wire:click="selectWallet({{ $wallet->id }})"
                                    class="cursor-pointer rounded-xl border p-4 transition {{ $selectedWallet == $wallet->id ? 'border-purple-500 bg-slate-800' : 'border-slate-700 bg-slate-900' }}"
                                >
                                    <div class="flex items-center justify-between gap-3">
                                        <div>
                                            <p class="font-bold text-white">{{ $wallet->name }}</p>
                                            <p class="break-all text-sm text-slate-400">{{ $wallet->account_identifier }}</p>
                                        </div>
                                        @if($selectedWallet == $wallet->id)
                                            <span class="text-sm font-bold text-green-400">Selected</span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @error('selectedWallet') <p class="text-sm text-red-400">{{ $message }}</p> @enderror

                    @if($selectedWallet)
                        <button wire:click="$set('showWalletPreview', true)" type="button" class="text-sm font-semibold text-purple-400 hover:text-purple-300">
                            Preview Selected Wallet for QR-Code
                        </button>
                    @endif

                    <div>
                        <label class="text-sm text-slate-400">Payment Screenshot</label>
                        <label for="brahmaProofImage" class="group mt-2 flex cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed border-slate-600 bg-slate-800/40 p-6 transition hover:border-indigo-500 hover:bg-slate-800">
                            @if($proofImage)
                                <img src="{{ $proofImage->temporaryUrl() }}" class="max-h-52 rounded-xl border-2 border-indigo-500 object-cover">
                                <span class="mt-3 text-indigo-300">Click to change screenshot</span>
                            @else
                                <span class="text-slate-300">Upload Payment Screenshot</span>
                                <span class="mt-1 text-xs text-slate-500">Click to browse</span>
                            @endif
                            <input id="brahmaProofImage" type="file" wire:model="proofImage" class="hidden" accept="image/*">
                        </label>
                        @error('proofImage') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
                        <div wire:loading wire:target="proofImage" class="mt-2 text-sm text-indigo-400">Uploading image...</div>
                    </div>
                </div>

                <div class="flex justify-end gap-3 border-t border-slate-800 p-5">
                    <button wire:click="closeModal" class="rounded-xl bg-gray-700 px-4 py-2">Cancel</button>
                    <button wire:click="submitDeposit" wire:loading.attr="disabled" wire:target="submitDeposit" class="rounded-xl bg-green-600 px-4 py-2 font-bold disabled:opacity-70">
                        <span wire:loading.remove wire:target="submitDeposit">Submit Deposit</span>
                        <span wire:loading wire:target="submitDeposit">Submitting...</span>
                    </button>
                </div>
            </div>
            </div>
        </div>
    @endif
    </div>
    @endteleport

    @teleport('body')
    <div>
    @if($showWalletPreview && $this->selectedWalletModel)
        <div class="fixed inset-0 z-[10001] overflow-y-auto bg-black/80">
            <div class="flex min-h-full items-center justify-center p-4">
            <div class="w-full max-w-md rounded-2xl border border-slate-700 bg-slate-900 p-6 text-white">
                <div class="mb-6 flex items-center justify-between">
                    <h3 class="text-xl font-bold">Wallet Details</h3>
                    <button wire:click="$set('showWalletPreview', false)" class="text-xl">x</button>
                </div>

                <div class="space-y-4">
                    <p><span class="text-slate-400">Wallet Owner:</span> <span class="font-bold">{{ $this->selectedWalletModel->name }}</span></p>
                    <p><span class="text-slate-400">Payment Tag:</span> <span class="break-all font-bold text-purple-400">{{ $this->selectedWalletModel->account_identifier }}</span></p>

                    @if($this->selectedWalletModel->qr_image)
                        <div>
                            <p class="mb-2 text-sm text-slate-400">QR Code:</p>
                            <img src="{{ asset('storage/' . $this->selectedWalletModel->qr_image) }}" class="mx-auto w-64 rounded-xl border border-slate-700">
                        </div>
                    @endif
                </div>
            </div>
            </div>
        </div>
    @endif
    </div>
    @endteleport
</div>
