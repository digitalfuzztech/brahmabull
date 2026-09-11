<div class="bb-player-page bb-notifications-page mx-auto max-w-7xl px-4 py-8 sm:px-6 sm:py-12">
    <header class="bb-player-page-heading">
        <div class="bb-player-kicker">Player Activity</div>
        <h1>Notifications</h1>
        <p>Review account updates, game access details, and transaction activity.</p>
    </header>

    <section class="bb-notification-filters" aria-label="Notification filters">
        <label class="bb-filter-field bb-filter-field--search">
            <span>Search</span>
            <span class="bb-filter-control">
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m21 21-4.35-4.35m1.35-5.65a7 7 0 1 1-14 0 7 7 0 0 1 14 0Z" />
                </svg>
                <input
                    wire:model.live="search"
                    type="search"
                    placeholder="Search notifications..."
                />
            </span>
        </label>

        <label class="bb-filter-field">
            <span>Category</span>
            <select wire:model.live="type">
                <option value="">All Types</option>
                <option value="deposit">Deposits</option>
                <option value="cashout">Cashouts</option>
                <option value="game">Games</option>
                <option value="brahma">Brahma</option>
            </select>
        </label>

        <label class="bb-filter-field">
            <span>Status</span>
            <select wire:model.live="readStatus">
                <option value="">All Status</option>
                <option value="0">Unread</option>
                <option value="1">Read</option>
            </select>
        </label>
    </section>

    <div class="bb-notification-list">
        @forelse($notifications as $notification)
            <article
                wire:key="notification-{{ $notification->id }}"
                class="bb-notification-card {{ $notification->is_read ? 'is-read' : 'is-unread' }}"
            >
                <div class="bb-notification-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 1 0-12 0v3.2a2 2 0 0 1-.6 1.4L4 17h5m6 0v1a3 3 0 0 1-6 0v-1m6 0H9" />
                    </svg>
                </div>

                <div class="bb-notification-copy">
                    <div class="bb-notification-meta">
                        <h2>{{ $notification->title }}</h2>
                        <time datetime="{{ $notification->created_at->toIso8601String() }}">
                            {{ $notification->created_at->diffForHumans() }}
                        </time>
                    </div>

                    <p class="whitespace-pre-line">{{ $notification->message }}</p>

                    <div class="bb-notification-actions">
                        @if($notification->type === 'cashout_paid')
                            <button
                                wire:click="viewProof({{ $notification->id }})"
                                class="bb-notification-action bb-notification-action--primary"
                            >
                                View Proof
                            </button>
                        @elseif(in_array($notification->type, ['deposit_verified', 'brahma_play_verified', 'brahma_balance_loaded', 'brahma_balance_adjusted', 'brahma_play_submitted']))
                            <button
                                wire:click="openNotification({{ $notification->id }})"
                                class="bb-notification-action bb-notification-action--primary"
                            >
                                {{ $notification->action_text ?: 'Play' }}
                            </button>
                        @elseif(
                            in_array(
                                $notification->type,
                                [
                                    'deposit_submitted',
                                    'deposit_rejected',
                                    'cashout_submitted',
                                    'cashout_rejected',
                                    'brahma_deposit_submitted',
                                    'brahma_deposit_rejected',
                                    'brahma_play_rejected'
                                ]
                            )
                        )
                            @if($notification->is_read)
                                <button
                                    wire:click="acknowledge({{ $notification->id }})"
                                    class="bb-notification-action bb-notification-action--confirmed"
                                >
                                    ✓ Got It
                                </button>
                            @else
                                <button
                                    wire:click="acknowledge({{ $notification->id }})"
                                    class="bb-notification-action bb-notification-action--muted"
                                >
                                    Got It
                                </button>
                            @endif
                        @else
                            @if($notification->is_read)
                                <button
                                    wire:click="toggleRead({{ $notification->id }})"
                                    class="bb-notification-action bb-notification-action--muted"
                                >
                                    Mark Unread
                                </button>
                            @else
                                <button
                                    wire:click="toggleRead({{ $notification->id }})"
                                    class="bb-notification-action bb-notification-action--primary"
                                >
                                    Mark Read
                                </button>
                            @endif
                        @endif
                    </div>
                </div>
            </article>
        @empty
            <div class="bb-player-empty">
                <span class="bb-player-empty__icon" aria-hidden="true">◇</span>
                <h2>No notifications yet</h2>
                <p>Account and game updates will appear here.</p>
            </div>
        @endforelse
    </div>

    <div class="mt-6 custom-page-styles">
        {{ $notifications->links() }}
    </div>

    @if(!empty($previewImage))
        <div class="bb-proof-preview fixed inset-0 z-[9999] flex items-center justify-center bg-black/80 p-4">
            <div class="relative max-h-[90vh] w-full max-w-3xl overflow-auto rounded-2xl border border-white/10 bg-slate-900 p-4 shadow-2xl">
                <button
                    wire:click="closePreview"
                    class="absolute right-3 top-3 grid h-10 w-10 place-items-center rounded-full bg-red-600 text-white"
                    aria-label="Close payment proof preview"
                >
                    ×
                </button>

                <img
                    src="{{ $previewImage }}"
                    class="mx-auto max-h-[80vh] rounded-xl object-contain"
                    alt="Cashout payment proof"
                >
            </div>
        </div>
    @endif
</div>
