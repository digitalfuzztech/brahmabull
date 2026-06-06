<div
    class="relative"
    wire:poll.5s
>

    {{-- BELL --}}
    <button
        wire:click="toggle"
        class="relative text-white"
    >

        <svg
            xmlns="http://www.w3.org/2000/svg"
            class="h-6 w-6"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
        >
            <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M15 17h5l-1.405-1.405A2.032
                2.032 0 0118 14.158V11
                a6.002 6.002 0 00-4-5.659V4
                a2 2 0 10-4 0v1.341C7.67
                6.165 6 8.388 6 11v3.159
                c0 .538-.214 1.055-.595
                1.436L4 17h5m6 0v1
                a3 3 0 11-6 0v-1m6 0H9"
            />
        </svg>

        @if($this->unreadCount)

            <span
                class="absolute -top-2 -right-2
                bg-red-600 text-white
                text-[10px]
                rounded-full
                min-w-[18px]
                h-[18px]
                flex items-center justify-center"
            >
                {{ $this->unreadCount }}
            </span>

        @endif

    </button>

    @if($open)

        <div
            class="absolute right-0 mt-2 w-[380px]
    bg-slate-900 border border-slate-700
    rounded-2xl overflow-hidden
    shadow-2xl z-[999]"
        >

            {{-- HEADER --}}
            <div class="p-4 border-b border-slate-700">

                <h3 class="font-bold text-white">
                    Notifications
                </h3>

                <p class="text-sm text-slate-400 mt-1">
                    {{ $this->unreadCount }} unread notifications
                </p>

            </div>

            {{-- SCROLLABLE AREA --}}
            <div class="max-h-[400px] overflow-y-auto">

                @forelse($this->unreadNotifications as $notification)

                    <div
                        class="p-4 border-b border-slate-800 hover:bg-slate-800/50 transition"
                    >

                        <div class="flex items-start justify-between gap-3">

                            <div class="flex-1">

                                <h4 class="font-semibold text-white">
                                    {{ $notification->title }}
                                </h4>

                                <p class="text-sm text-slate-300 mt-1 line-clamp-2">
                                    {{ $notification->message }}
                                </p>

                                <p class="text-xs text-slate-500 mt-2">
                                    {{ $notification->created_at->diffForHumans() }}
                                </p>

                            </div>

                            <a
                                href="{{ route('player.notifications') }}"
                                class="text-xs text-indigo-400 hover:text-indigo-300 whitespace-nowrap"
                            >
                                View →
                            </a>

                        </div>

                    </div>

                @empty

                    <div class="p-6 text-center text-slate-400">
                        No unread notifications.
                    </div>

                @endforelse

            </div>

            {{-- FOOTER --}}
            <div
                class="border-t border-slate-700 p-4 bg-slate-950"
            >

                <a
                    href="{{ route('player.notifications') }}"
                    class="block text-center font-semibold text-indigo-400 hover:text-indigo-300"
                >
                    Go To Notifications Page
                </a>

            </div>

        </div>

    @endif

</div>
