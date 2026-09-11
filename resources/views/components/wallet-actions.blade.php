@props(['wallet'])

@php
    $cashtag = trim((string) $wallet?->account_identifier);
    $qrPath = trim((string) $wallet?->qr_image);
@endphp

@if($cashtag !== '' || $qrPath !== '')
    <div class="bb-wallet-actions space-y-3" data-wallet-actions>
        @if($cashtag !== '')
            <div
                class="bb-wallet-cashtag"
                x-data="{
                    copied: false,
                    value: @js($cashtag),
                    async copyCashtag() {
                        try {
                            await navigator.clipboard.writeText(this.value);
                        } catch (error) {
                            const field = document.createElement('textarea');
                            field.value = this.value;
                            field.setAttribute('readonly', '');
                            field.style.position = 'fixed';
                            field.style.opacity = '0';
                            document.body.appendChild(field);
                            field.select();
                            document.execCommand('copy');
                            field.remove();
                        }

                        this.copied = true;
                        window.setTimeout(() => this.copied = false, 1800);
                    }
                }"
                data-wallet-cashtag="{{ $cashtag }}"
            >
                <div class="min-w-0">
                    <span class="bb-wallet-action-label">Cashtag</span>
                    <strong class="block break-all text-sm text-purple-200">{{ $cashtag }}</strong>
                </div>

                <button
                    type="button"
                    class="bb-wallet-action-button shrink-0"
                    x-on:click.stop="copyCashtag"
                    aria-label="Copy cashtag"
                >
                    <span x-show="!copied">Copy</span>
                    <span x-cloak x-show="copied">Copied!</span>
                </button>
            </div>
        @endif

        @if($qrPath !== '')
            <a
                href="{{ asset('storage/'.$qrPath) }}"
                download="brahmabull-wallet-qr.png"
                class="bb-wallet-action-button inline-flex"
                aria-label="Download wallet QR"
                data-wallet-qr-download
                x-on:click.stop
            >
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v12m0 0 4-4m-4 4-4-4M5 19h14" />
                </svg>
                Download QR
            </a>
        @endif
    </div>
@endif
