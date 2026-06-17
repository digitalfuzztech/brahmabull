<div>

    {{-- HEADER --}}
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-white">
            My Agent Dashboard
        </h1>

        <p class="text-slate-400 text-sm">
            Overview of your activity
        </p>
    </div>

    {{-- STATS --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">

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

    {{-- DEPOSITS --}}
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

    {{-- CASHOUTS --}}
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

    {{-- WALLETS --}}
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

</div>
