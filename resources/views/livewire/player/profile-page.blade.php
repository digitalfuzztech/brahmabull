<div class="max-w-7xl mx-auto px-4 py-8">

    @if(session('success'))
        <div class="mb-6 p-4 rounded-xl bg-green-500/10 border border-green-500/20 text-green-400">
            {{ session('success') }}
        </div>
    @endif

    {{-- PROFILE DETAILS --}}

    <div class="rounded-3xl bg-slate-900 border border-slate-800 p-6">

        <div class="flex flex-col md:flex-row gap-6 items-center">

            <img
                src="{{ auth()->user()->photo
                    ? Storage::url(auth()->user()->photo)
                    : asset('images/default-user.png') }}"
                class="w-32 h-32 rounded-full object-cover border-4 border-purple-500"
            >

            <div class="flex-1">

                <h2 class="text-3xl font-black">
                    {{ auth()->user()->name }}
                </h2>

                <div class="mt-3 space-y-1 text-slate-300">

                    <div>
                        Email:
                        {{ auth()->user()->email }}
                    </div>

                    <div>
                        Phone:
                        {{ auth()->user()->phone ?: 'Not set' }}
                    </div>

                    <div>
                        Referral Code:
                        <span class="text-purple-400">
                            {{ auth()->user()->referral_code }}
                        </span>
                    </div>

                    <div class="flex flex-wrap gap-3 items-center">

                        <span>
                            Invite Link:
                        </span>

                        <input
                            readonly
                            value="{{ route('register',['ref'=>auth()->user()->referral_code]) }}"
                            id="invite-link"
                            class="bg-slate-800 rounded px-3 py-1 text-sm w-full md:w-[420px]"
                        >

                        <button
                            onclick="
                                navigator.clipboard.writeText(
                                    document.getElementById('invite-link').value
                                )
                            "
                            class="px-4 py-2 rounded-xl bg-purple-600"
                        >
                            Copy
                        </button>

                    </div>

                </div>

            </div>

            <button
                wire:click="$set('showEditModal',true)"
                class="px-5 py-3 rounded-xl bg-purple-600"
            >
                Edit Profile
            </button>

        </div>

    </div>

    {{-- STATS --}}

    <div class="grid md:grid-cols-3 gap-6 mt-8">

        <div class="bg-slate-900 rounded-3xl p-6 border border-slate-800">
            <div class="text-slate-400">Total Deposits</div>
            <div class="text-3xl font-black mt-2">
                ${{ number_format($totalDeposits) }}
            </div>
        </div>

        <div class="bg-slate-900 rounded-3xl p-6 border border-slate-800">
            <div class="text-slate-400">Total Cashouts</div>
            <div class="text-3xl font-black mt-2">
                ${{ number_format($totalCashouts) }}
            </div>
        </div>

        <div class="bg-slate-900 rounded-3xl p-6 border border-slate-800">
            <div class="text-slate-400">Total Referrals</div>
            <div class="text-3xl font-black mt-2">
                {{ number_format($totalReferrals) }}
            </div>
        </div>

    </div>

        {{-- MONTHLY SECTION --}}

        <div class="mt-8 rounded-3xl bg-slate-900 border border-slate-800 p-6">

            <div class="flex justify-between items-center">

                <button
                    wire:click="previousMonth"
                    class="px-4 py-2 bg-slate-800 rounded-xl"
                >
                    ←
                </button>

                <h3 class="text-xl font-black">
                    {{ \Carbon\Carbon::create()
                        ->month($month)
                        ->format('F') }}
                    {{ $year }}
                </h3>

                <button
                    wire:click="nextMonth"
                    class="px-4 py-2 bg-slate-800 rounded-xl"
                >
                    →
                </button>

            </div>

            {{-- TAB CARDS --}}

            <div class="grid md:grid-cols-3 gap-6 mt-6">

                <button
                    wire:click="$set('activeTab','deposits')"
                    class="rounded-2xl p-5 text-left border
            {{ $activeTab === 'deposits'
                ? 'bg-purple-600 border-purple-500'
                : 'bg-slate-800 border-slate-700' }}"
                >
                    <div>Deposits</div>

                    <div class="text-2xl font-black mt-2">
                        ${{ number_format($monthDeposits) }}
                    </div>
                </button>

                <button
                    wire:click="$set('activeTab','cashouts')"
                    class="rounded-2xl p-5 text-left border
            {{ $activeTab === 'cashouts'
                ? 'bg-purple-600 border-purple-500'
                : 'bg-slate-800 border-slate-700' }}"
                >
                    <div>Cashouts</div>

                    <div class="text-2xl font-black mt-2">
                        ${{ number_format($monthCashouts) }}
                    </div>
                </button>

                <button
                    wire:click="$set('activeTab','referrals')"
                    class="rounded-2xl p-5 text-left border
            {{ $activeTab === 'referrals'
                ? 'bg-purple-600 border-purple-500'
                : 'bg-slate-800 border-slate-700' }}"
                >
                    <div>Referrals</div>

                    <div class="text-2xl font-black mt-2">
                        {{ number_format($monthReferrals) }}
                    </div>
                </button>

            </div>

            {{-- DEPOSITS TABLE --}}

            @if($activeTab === 'deposits')

                <div class="overflow-x-auto mt-8">

                    <table class="w-full text-sm">

                        <thead>

                        <tr class="border-b border-slate-700">

                            <th class="text-left py-3">Date</th>
                            <th class="text-left py-3">Reference</th>
                            <th class="text-left py-3">Game</th>
                            <th class="text-left py-3">Username</th>
                            <th class="text-left py-3">Amount</th>
                            <th class="text-left py-3">Points Loaded</th>

                        </tr>

                        </thead>

                        <tbody>

                        @forelse($depositRows as $row)

                            <tr class="border-b border-slate-800">

                                <td class="py-3">
                                    {{ $row->verified_at?->format('Y-m-d') }}
                                </td>

                                <td class="py-3">{{ $row->reference }}</td>

                                <td>{{ $row->game?->name }}</td>

                                <td class="py-3">
                                    {{ $gameAccounts[$row->user_id.'-'.$row->game_id]->game_username ?? '-' }}
                                </td>

                                <td class="py-3"> ${{ number_format($row->amount) }}</td>

                                <td class="py-3">
                                    {{ number_format($row->game_points_loaded) }}
                                </td>

                            </tr>

                        @empty

                            <tr>
                                <td colspan="6" class="py-6 text-center">
                                    No deposits found.
                                </td>
                            </tr>

                        @endforelse

                        </tbody>

                    </table>

                </div>

            @endif

            {{-- CASHOUT TABLE --}}

            @if($activeTab === 'cashouts')

                <div class="overflow-x-auto mt-8">

                    <table class="w-full text-sm">

                        <thead>

                        <tr class="border-b border-slate-700">

                            <th class="text-left py-3">Date</th>
                            <th class="text-left py-3">Reference</th>
                            <th class="text-left py-3">Game</th>
                            <th class="text-left py-3">Username</th>
                            <th class="text-left py-3">Amount</th>
                            <th class="text-left py-3">Points Redeemed</th>

                        </tr>

                        </thead>

                        <tbody>

                        @forelse($cashoutRows as $row)

                            <tr class="border-b border-slate-800">

                                <td class="py-3">{{ $row->paid_at?->format('Y-m-d') }}</td>

                                <td>{{ $row->reference }}</td>

                                <td class="py-3">{{ $row->game?->name }}</td>

                                <td class="py-3">
                                    {{ $gameAccounts[$row->user_id.'-'.$row->game_id]->game_username ?? '-' }}
                                </td>

                                <td class="py-3">
                                    ${{ number_format($row->amount) }}
                                </td>

                                <td class="py-3">
                                    {{ number_format($row->amount) }}
                                </td>

                            </tr>

                        @empty

                            <tr>
                                <td colspan="6" class="py-6 text-center">
                                    No cashouts found.
                                </td>
                            </tr>

                        @endforelse

                        </tbody>

                    </table>

                </div>

            @endif

            {{-- REFERRALS TABLE --}}

            @if($activeTab === 'referrals')

                <div class="overflow-x-auto mt-8">

                    <table class="w-full text-sm">

                        <thead>

                        <tr class="border-b border-slate-700">

                            <th class="text-left py-3">Date</th>
                            <th class="text-left py-3">Player Name</th>
                            <th class="text-left py-3">Phone</th>
                            <th class="text-left py-3">Referral ID</th>

                        </tr>

                        </thead>

                        <tbody>

                        @forelse($referralRows as $row)

                            <tr class="border-b border-slate-800">

                                <td class="py-3">
                                    {{ $row->created_at->format('Y-m-d') }}
                                </td>

                                <td class="py-3">
                                    {{ $row->referredUser->name }}
                                </td>

                                <td class="py-3">
                                    {{ $row->referredUser->phone }}
                                </td>

                                <td class="py-3">
                                    {{ $row->referredUser->referral_code }}
                                </td>

                            </tr>

                        @empty

                            <tr>
                                <td colspan="4" class="py-6 text-center">
                                    No referrals found.
                                </td>
                            </tr>

                        @endforelse

                        </tbody>

                    </table>

                </div>

            @endif

        </div>
    {{-- EDIT MODAL --}}

        @if($showEditModal)

            <div class="fixed inset-0 z-[99999] flex justify-center bg-black/70 backdrop-blur-sm">

                <div class="w-full max-w-lg h-[70vh] flex flex-col m-auto rounded-3xl bg-slate-900 border border-slate-800 overflow-hidden">

                    {{-- HEADER --}}
                    <div class="flex justify-between items-center p-6 border-b border-slate-800">
                        <h2 class="text-2xl font-black">
                            Edit Profile
                        </h2>

                        <button wire:click="closeModal" class="text-white text-xl">
                            ✕
                        </button>
                    </div>

                    {{-- ERROR --}}
                    @error('current_password')
                    <div class="mx-6 mt-4 p-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-400">
                        {{ $message }}
                    </div>
                    @enderror
                    <form
                        wire:submit="saveProfile"
                        wire:loading.attr="disabled"
                        wire:target="saveProfile"
                        class="flex flex-col flex-1 min-h-0"
                    >
                    {{-- SCROLLABLE BODY --}}
                    <div class="flex-1 overflow-y-auto p-6 space-y-6 min-h-0 custom-scrollbar">

                        {{-- PHONE --}}
                        <div>
                            <label>Phone Number</label>

                            <input
                                wire:model="phone"
                                type="text"
                                class="w-full mt-2 bg-slate-800 rounded-xl p-3"
                            >
                        </div>

                        {{-- PHOTO --}}
                        <div>
                            <label class="text-sm text-slate-400">Profile Photo</label>

                            <label
                                for="photo"
                                class="group mt-2 flex cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed border-slate-600 bg-slate-800/40 p-6 transition hover:border-purple-500 hover:bg-slate-800"
                            >

                                @if($photo)

                                    <img
                                        src="{{ $photo->temporaryUrl() }}"
                                        class="max-h-52 rounded-xl object-cover border-2 border-purple-500"
                                    >

                                    <span class="mt-3 text-purple-300">
                            Click to change photo
                        </span>

                                @else

                                    <svg
                                        xmlns="http://www.w3.org/2000/svg"
                                        class="h-12 w-12 text-slate-400 group-hover:text-purple-400"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                    >
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M3 7l6-6m0 0l6 6m-6-6v18" />
                                    </svg>

                                    <span class="mt-4 text-slate-300">
                            Upload Profile Photo
                        </span>

                                    <span class="text-xs text-slate-500 mt-1">
                            Click to browse
                        </span>

                                @endif

                                <input
                                    id="photo"
                                    type="file"
                                    wire:model="photo"
                                    class="hidden"
                                    accept="image/*"
                                />
                            </label>

                            <div wire:loading wire:target="photo" class="mt-3 text-sm text-purple-400">
                                Uploading image...
                            </div>
                        </div>

                        {{-- PASSWORD --}}
                        <div x-data="{ show:false }">
                            <label>New Password</label>

                            <div class="relative mt-2">

                                <input
                                    wire:model="password"
                                    x-bind:type="show ? 'text' : 'password'"
                                    class="w-full bg-slate-800 rounded-xl p-3 pr-12"
                                >

                                <button
                                    type="button"
                                    @click="show = !show"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-purple-400"
                                >

                                    <svg x-show="!show"
                                         xmlns="http://www.w3.org/2000/svg"
                                         class="h-5 w-5"
                                         fill="none"
                                         viewBox="0 0 24 24"
                                         stroke="currentColor">
                                        <path stroke-linecap="round"
                                              stroke-linejoin="round"
                                              stroke-width="2"
                                              d="M15 12a3 3 0 11-6 0 3 3 0 016 0zm6 0c-1.73 3.89-6 7-11 7s-9.27-3.11-11-7c1.73-3.89 6-7 11-7s9.27 3.11 11 7z"/>
                                    </svg>

                                    <svg x-show="show"
                                         xmlns="http://www.w3.org/2000/svg"
                                         class="h-5 w-5"
                                         fill="none"
                                         viewBox="0 0 24 24"
                                         stroke="currentColor">
                                        <path stroke-linecap="round"
                                              stroke-linejoin="round"
                                              stroke-width="2"
                                              d="M13.875 18.825A10.05 10.05 0 0112 19c-5 0-9.27-3.11-11-7 1.02-2.29 2.76-4.2 4.96-5.42M9.88 9.88A3 3 0 0114.12 14.12M3 3l18 18"/>
                                    </svg>

                                </button>

                            </div>
                        </div>

                        <div x-data="{ show:false }">
                            <label>Confirm Password</label>

                            <div class="relative mt-2">

                                <input
                                    wire:model="password_confirmation"
                                    x-bind:type="show ? 'text' : 'password'"
                                    class="w-full bg-slate-800 rounded-xl p-3 pr-12"
                                >

                                <button
                                    type="button"
                                    @click="show = !show"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-purple-400"
                                >

                                    <svg x-show="!show"
                                         xmlns="http://www.w3.org/2000/svg"
                                         class="h-5 w-5"
                                         fill="none"
                                         viewBox="0 0 24 24"
                                         stroke="currentColor">
                                        <path stroke-linecap="round"
                                              stroke-linejoin="round"
                                              stroke-width="2"
                                              d="M15 12a3 3 0 11-6 0 3 3 0 016 0zm6 0c-1.73 3.89-6 7-11 7s-9.27-3.11-11-7c1.73-3.89 6-7 11-7s9.27 3.11 11 7z"/>
                                    </svg>

                                    <svg x-show="show"
                                         xmlns="http://www.w3.org/2000/svg"
                                         class="h-5 w-5"
                                         fill="none"
                                         viewBox="0 0 24 24"
                                         stroke="currentColor">
                                        <path stroke-linecap="round"
                                              stroke-linejoin="round"
                                              stroke-width="2"
                                              d="M13.875 18.825A10.05 10.05 0 0112 19c-5 0-9.27-3.11-11-7 1.02-2.29 2.76-4.2 4.96-5.42M9.88 9.88A3 3 0 0114.12 14.12M3 3l18 18"/>
                                    </svg>

                                </button>

                            </div>
                        </div>

                        <div>
                            <label>Current Password</label>

                            <input
                                wire:model="current_password"
                                type="password"
                                class="w-full mt-2 bg-slate-800 rounded-xl p-3"
                            >
                        </div>

                    </div>

                    {{-- FOOTER --}}
                    <div class="p-6 border-t border-slate-800 flex justify-end gap-3">

                        <button
                            type="button"
                            wire:click="closeModal"
                            class="px-4 py-2 border border-slate-700 rounded-xl"
                        >
                            Cancel
                        </button>

                        <button
                            type="submit"
                            wire:loading.attr="disabled"
                            wire:target="saveProfile"
                            class="px-5 py-2 bg-purple-600 rounded-xl"
                        >
                <span wire:loading.remove wire:target="saveProfile">
                    Save
                </span>

                            <span wire:loading wire:target="saveProfile">
                    Saving...
                </span>
                        </button>

                    </div>
                    </form>
                </div>
            </div>

        @endif

</div>
