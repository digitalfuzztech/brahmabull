<div>

        @guest
        <form wire:submit="send" class="bb-footer-email-form">

            <input
                wire:model="email"
                type="email"
                placeholder="Enter your email"
                class="bb-footer-input"
            >

            <button
                type="submit"
                wire:loading.attr="disabled"
                wire:target="send"
                class="bb-footer-send-button disabled:opacity-60"
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
                    <form
                        wire:submit="send"
                        class="bb-footer-message-form"
                    >
        <input
            wire:model="subject"
            type="text"
            placeholder="Your Subject"
            class="bb-footer-input"
        >
        <textarea
            wire:model="message"
            type="message"
            placeholder="Enter your message"
            class="bb-footer-textarea"
        > </textarea>

            <button
                type="submit"
                wire:loading.attr="disabled"
                wire:target="send"
                class="bb-footer-send-button bb-footer-send-button-full disabled:opacity-60"
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
                    <form
                        wire:submit="send"
                        class="bb-footer-message-form"
                    >
                        <input
                            wire:model="subject"
                            type="text"
                            placeholder="Your Subject"
                            class="bb-footer-input"
                        >
                        <textarea
                            wire:model="message"
                            type="message"
                            placeholder="Enter your message"
                            class="bb-footer-textarea"
                        > </textarea>
                            <button
                                type="submit"
                                wire:loading.attr="disabled"
                                wire:target="send"
                                class="bb-footer-send-button bb-footer-send-button-full disabled:opacity-60"
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
    <div class="bb-footer-error">
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
            class="bb-footer-success"
        >
            Email sent, we will contact you soon.
        </div>

    @endif

</div>
