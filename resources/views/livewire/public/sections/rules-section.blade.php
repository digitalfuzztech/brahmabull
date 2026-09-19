@php
    $ruleSummaries = [
        [
            'number' => '01',
            'title' => 'Account',
            'copy' => 'Use your own player account and keep login and game credentials private.',
        ],
        [
            'number' => '02',
            'title' => 'Deposits',
            'copy' => 'Choose an available payment option and submit the correct amount, wallet, and payment proof.',
        ],
        [
            'number' => '03',
            'title' => 'Brahma Balance',
            'copy' => 'Use only your available balance; play requests require enough balance and staff verification.',
        ],
        [
            'number' => '04',
            'title' => 'Credentials',
            'copy' => 'Use the game credentials assigned to your account and the configured Play action.',
        ],
        [
            'number' => '05',
            'title' => 'Withdrawals',
            'copy' => 'Provide the correct game account, amount, payment method, and wallet or QR details.',
        ],
        [
            'number' => '06',
            'title' => 'Support',
            'copy' => 'Use Player Support Chat for help and provide accurate request information.',
        ],
    ];
@endphp


<section id="rules" class="bb-rules-section">

    <div class="bb-rules-wrap">

        {{-- ========================================================= --}}
        {{-- DECORATIVE FLOATING PARTICLES                            --}}
        {{-- ========================================================= --}}

        <span class="bb-rules-sparkle sparkle-1">✦</span>
        <span class="bb-rules-sparkle sparkle-2">✧</span>
        <span class="bb-rules-sparkle sparkle-3">✦</span>
        <span class="bb-rules-sparkle sparkle-4">✧</span>
        <span class="bb-rules-sparkle sparkle-5">✦</span>


        {{-- FLOATING CASINO LABELS --}}
        <div class="bb-rule-floating-label bb-rule-floating-label-left">
            <span class="bb-floating-dot"></span>
            SECURE PLAY
        </div>

        <div class="bb-rule-floating-label bb-rule-floating-label-right">
            VERIFIED FLOW
            <span class="bb-floating-dot"></span>
        </div>


        {{-- ========================================================= --}}
        {{-- MARQUEE                                                   --}}
        {{-- ========================================================= --}}

        <div data-bb-reveal="scale" class="bb-rules-marquee">


            {{-- glow behind frame --}}
            <div class="bb-rules-frame-glow" aria-hidden="true"></div>


            {{-- FRAME IMAGE --}}
            <img
                src="{{ asset('images/ui/casino/rules-frame.png') }}"
                alt=""
                aria-hidden="true"
                class="bb-rules-frame-image"
            >


            {{-- moving shine --}}
            <div class="bb-rules-marquee-shine" aria-hidden="true"></div>


            {{-- ===================================================== --}}
            {{-- CONTENT INSIDE ACTUAL RED AREA                       --}}
            {{-- ===================================================== --}}

            <div class="bb-rules-frame-content">


                {{-- HEADER --}}
                <header class="bb-rules-header">

                    <div class="bb-rules-eyebrow">

                        <span class="bb-rules-eyebrow-line"></span>

                        <span>
                            Play With Confidence
                        </span>

                        <span class="bb-rules-eyebrow-line"></span>

                    </div>


                    <h2 class="bb-rules-title">
                        BRAHMABULL

                        <span>
                            RULES
                        </span>
                    </h2>


                    <p class="bb-rules-subtitle">
                        Know the rules.
                        Protect your account.
                        Play with confidence.
                    </p>

                </header>


                {{-- ORNAMENT --}}
                <div class="bb-rules-ornament">

                    <span></span>

                    <i>✦</i>

                    <span></span>

                </div>



                {{-- ================================================= --}}
                {{-- RULE CARDS                                       --}}
                {{-- ================================================= --}}

                <div class="bb-rules-grid">

                    @foreach($ruleSummaries as $rule)

                        <article
                            data-bb-reveal
                            style="--bb-reveal-delay: {{ $loop->index * 70 }}ms"
                            class="bb-rule-card"
                        >

                            {{-- background giant number --}}
                            <span class="bb-rule-watermark">
                                {{ $rule['number'] }}
                            </span>


                            {{-- top glow --}}
                            <div class="bb-rule-card-glow"></div>


                            {{-- medallion --}}
                            <div class="bb-rule-number">

                                <span>
                                    {{ $rule['number'] }}
                                </span>

                            </div>


                            {{-- content --}}
                            <div class="bb-rule-body">

                                <div class="bb-rule-mini-label">
                                    PLAYER RULE
                                </div>

                                <h3>
                                    {{ $rule['title'] }}
                                </h3>

                                <p>
                                    {{ $rule['copy'] }}
                                </p>

                            </div>


                            {{-- corner spark --}}
                            <span class="bb-rule-corner-spark">
                                ✦
                            </span>

                        </article>

                    @endforeach

                </div>


                {{-- ================================================= --}}
                {{-- FOOTER / BUTTON                                  --}}
                {{-- ================================================= --}}

                <div
                    data-bb-reveal
                    style="--bb-reveal-delay: 450ms"
                    class="bb-rules-footer"
                >

                    <span class="bb-rules-footer-line"></span>


                    <a
                        href="{{ route('brahmabull-rules') }}"
                        class="bb-rules-button"
                    >

                        <span class="bb-rules-button-glow"></span>

                        <span class="relative z-10">
                            View All Rules
                        </span>

                        <svg
                            class="relative z-10"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M5 12h14m-6-6 6 6-6 6"
                            />
                        </svg>

                    </a>


                    <span class="bb-rules-footer-line"></span>

                </div>

            </div>

        </div>

    </div>

</section>
