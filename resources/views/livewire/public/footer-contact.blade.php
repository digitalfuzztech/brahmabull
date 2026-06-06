<div>

        @guest
        <form wire:submit="send" class="flex gap-3">

            <input
                wire:model="email"
                type="email"
                placeholder="Enter your email"
                class="flex-1 rounded-xl border border-slate-700 bg-slate-900 px-4 py-3 text-white"
            >

            <button
                type="submit"
                wire:loading.attr="disabled"
                wire:target="send"
                class="rounded-xl bg-gradient-to-r from-purple-600 to-indigo-600 px-6 py-3 font-bold disabled:opacity-70"
            >

    <span wire:loading.remove wire:target="send">
        Send
    </span>

                <span
                    wire:loading
                    wire:target="send"
                    class="flex items-center gap-2"
                >

        <svg
            class="h-4 w-4 animate-spin"
            viewBox="0 0 24 24"
            fill="none"
        >
            <circle
                class="opacity-25"
                cx="12"
                cy="12"
                r="10"
                stroke="currentColor"
                stroke-width="4"
            ></circle>

            <path
                class="opacity-75"
                fill="currentColor"
                d="M4 12a8 8 0 018-8v8z"
            ></path>
        </svg>

        Sending...
    </span>

            </button>

        </form>
        @endguest
    @auth
        @if(auth()->user()->hasRole('player'))
    <form wire:submit="send" class="flex flex-col gap-3">
        <input
            wire:model="subject"
            type="text"
            placeholder="Your Subject"
            class="flex-1 rounded-xl border border-slate-700 bg-slate-900 px-4 py-3 text-white"
        >
        <textarea
            wire:model="message"
            type="message"
            placeholder="Enter your message"
            class="flex-1 rounded-xl border border-slate-700 bg-slate-900 px-4 py-3 text-white"
        > </textarea>

            <button
                type="submit"
                wire:loading.attr="disabled"
                wire:target="send"
                class="rounded-xl bg-gradient-to-r from-purple-600 to-indigo-600 px-6 py-3 font-bold disabled:opacity-70"
            >

    <span wire:loading.remove wire:target="send">
        Send
    </span>

                <span
                    wire:loading
                    wire:target="send"
                    class="flex items-center gap-2"
                >

        <svg
            class="h-4 w-4 animate-spin"
            viewBox="0 0 24 24"
            fill="none"
        >
            <circle
                class="opacity-25"
                cx="12"
                cy="12"
                r="10"
                stroke="currentColor"
                stroke-width="4"
            ></circle>

            <path
                class="opacity-75"
                fill="currentColor"
                d="M4 12a8 8 0 018-8v8z"
            ></path>
        </svg>

        Sending...
    </span>

            </button>

    </form>

                @elseif(auth()->user()->hasRole('agent'))
                    <form wire:submit="send" class="flex flex-col gap-3">
                        <input
                            wire:model="subject"
                            type="text"
                            placeholder="Your Subject"
                            class="flex-1 rounded-xl border border-slate-700 bg-slate-900 px-4 py-3 text-white"
                        >
                        <textarea
                            wire:model="message"
                            type="message"
                            placeholder="Enter your message"
                            class="flex-1 rounded-xl border border-slate-700 bg-slate-900 px-4 py-3 text-white"
                        > </textarea>
                            <button
                                type="submit"
                                wire:loading.attr="disabled"
                                wire:target="send"
                                class="rounded-xl bg-gradient-to-r from-purple-600 to-indigo-600 px-6 py-3 font-bold disabled:opacity-70"
                            >

    <span wire:loading.remove wire:target="send">
        Send
    </span>

                                <span
                                    wire:loading
                                    wire:target="send"
                                    class="flex items-center gap-2"
                                >

        <svg
            class="h-4 w-4 animate-spin"
            viewBox="0 0 24 24"
            fill="none"
        >
            <circle
                class="opacity-25"
                cx="12"
                cy="12"
                r="10"
                stroke="currentColor"
                stroke-width="4"
            ></circle>

            <path
                class="opacity-75"
                fill="currentColor"
                d="M4 12a8 8 0 018-8v8z"
            ></path>
        </svg>

        Sending...
    </span>

                            </button>

                    </form>
            @elseif(auth()->user()->hasRole('admin'))

@endif
            @endauth
    @error('email')
    <div class="mt-2 text-sm text-red-400">
        {{ $message }}
    </div>
    @enderror

    @if($sent)

        <div
            x-data
            x-init="
            setTimeout(() => {
                $wire.hideSuccess()
            }, 5000)
        "
            class="mt-3 rounded-xl border border-green-500/30 bg-green-500/10 p-8 text-sm text-green-400"
        >
            Email sent, we will contact you soon.
        </div>

    @endif

</div>
