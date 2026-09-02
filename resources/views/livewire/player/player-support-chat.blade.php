<div
    x-data="{
        scrollToLatest() {
            this.$nextTick(() => {
                if (this.$refs.messages) {
                    this.$refs.messages.scrollTop = this.$refs.messages.scrollHeight;
                }
            });
        }
    }"
    x-on:support-chat-scroll.window="scrollToLatest()"
>
    @if($isOpen)
        <section
            wire:poll.3s.visible="pollOpen"
            x-init="scrollToLatest()"
            aria-label="Brahmabull Support Team chat"
            class="fixed inset-x-3 bottom-3 top-24 z-[9997] flex flex-col overflow-hidden rounded-3xl border border-purple-500/40 bg-slate-950 shadow-2xl shadow-purple-950/50 md:inset-auto md:bottom-24 md:right-6 md:h-[600px] md:max-h-[calc(100vh-7rem)] md:w-[400px]"
        >
            <header class="flex items-center justify-between border-b border-slate-800 bg-gradient-to-r from-purple-700/40 to-indigo-700/30 px-5 py-4">
                <div class="min-w-0">
                    <h2 class="truncate text-base font-black text-white">Brahmabull Support Team</h2>
                    <p class="text-xs text-purple-200">We're here to help</p>
                </div>

                <button
                    type="button"
                    wire:click="closeChat"
                    aria-label="Close support chat"
                    class="flex h-9 w-9 items-center justify-center rounded-full text-xl text-slate-300 transition hover:bg-white/10 hover:text-white"
                >
                    &times;
                </button>
            </header>

            <div x-ref="messages" class="min-h-0 flex-1 space-y-3 overflow-y-auto px-4 py-4">
                @foreach($messages as $chatMessage)
                    <article
                        wire:key="support-message-{{ $chatMessage['id'] }}"
                        class="flex {{ $chatMessage['is_player'] ? 'justify-end' : 'justify-start' }}"
                    >
                        <div class="max-w-[88%]">
                            <div class="mb-1 text-[11px] font-bold uppercase tracking-wide {{ $chatMessage['is_player'] ? 'text-right text-indigo-300' : 'text-purple-300' }}">
                                {{ $chatMessage['display_name'] }}
                            </div>

                            @if(filled($chatMessage['body']))
                                <div class="whitespace-pre-wrap break-words rounded-2xl px-3 py-2 text-sm leading-normal {{ $chatMessage['is_player'] ? 'rounded-br-md bg-indigo-600 text-white' : 'rounded-bl-md border border-slate-700 bg-slate-900 text-slate-100' }}">{{ trim($chatMessage['body']) }}</div>
                            @endif

                            @if(!$chatMessage['is_player'] && !empty($chatMessage['metadata']['options']))
                                <div class="mt-2 flex flex-wrap gap-2">
                                    @foreach($chatMessage['metadata']['options'] as $option)
                                        @if(isset($option['label'], $option['value']))
                                            <button
                                                type="button"
                                                wire:click="selectOption(@js($option['value']))"
                                                wire:loading.attr="disabled"
                                                wire:target="selectOption"
                                                @disabled($conversationStatus !== 'bot')
                                                class="rounded-full border border-purple-500/50 bg-purple-500/10 px-3 py-2 text-left text-xs font-bold text-purple-200 transition hover:bg-purple-500/25 disabled:cursor-not-allowed disabled:opacity-45"
                                            >
                                                {{ $option['label'] }}
                                            </button>
                                        @endif
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>

            <form wire:submit="sendMessage" class="border-t border-slate-800 bg-slate-900/90 p-3">
                @error('message')
                    <p class="mb-2 text-xs text-red-400">{{ $message }}</p>
                @enderror

                <div class="flex items-end gap-2">
                    <textarea
                        wire:model="message"
                        x-on:keydown.enter="if (!$event.shiftKey) { $event.preventDefault(); $wire.sendMessage(); }"
                        rows="1"
                        maxlength="2000"
                        placeholder="Type a message..."
                        aria-label="Support message"
                        class="max-h-28 min-h-[44px] flex-1 resize-none rounded-2xl border-slate-700 bg-slate-800 text-sm text-white placeholder:text-slate-500 focus:border-purple-500 focus:ring-purple-500"
                    ></textarea>

                    <button
                        type="submit"
                        wire:loading.attr="disabled"
                        wire:target="sendMessage"
                        class="flex h-11 items-center justify-center rounded-2xl bg-gradient-to-r from-purple-600 to-indigo-600 px-4 text-sm font-black text-white transition hover:brightness-110 disabled:opacity-60"
                    >
                        <span wire:loading.remove wire:target="sendMessage">Send</span>
                        <span wire:loading wire:target="sendMessage">...</span>
                    </button>
                </div>

                <p class="mt-2 px-1 text-[10px] text-slate-500">Press Enter to send. Shift+Enter adds a new line.</p>
            </form>
        </section>
    @else
        <div wire:poll.10s.visible="refreshUnread" class="fixed bottom-6 right-6 z-[9000]">
            <button
                type="button"
                wire:click="openChat"
                aria-label="Open Brahmabull support chat"
                class="relative flex h-16 w-16 items-center justify-center rounded-full border border-purple-400/60 bg-gradient-to-br from-purple-600 to-indigo-700 text-white shadow-2xl shadow-purple-950/60 transition hover:scale-105 hover:brightness-110"
            >
                <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4z" />
                </svg>

                @if($unreadCount > 0)
                    <span class="absolute -right-1 -top-1 flex min-h-6 min-w-6 items-center justify-center rounded-full bg-red-600 px-1.5 text-[11px] font-black text-white ring-2 ring-slate-950">
                        {{ $unreadCount > 99 ? '99+' : $unreadCount }}
                    </span>
                @endif
            </button>
        </div>
    @endif
</div>
