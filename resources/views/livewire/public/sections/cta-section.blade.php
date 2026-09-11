<section
    id="cta"
    class="bb-cta-section py-6 md:py-8"
>

    <div class="mx-auto max-w-[1380px] px-5 md:px-7">

        <div data-bb-reveal="scale" class="bb-cta-shell">

            {{-- GENERATED CTA BACKGROUND --}}
            <img
                src="{{ asset('images/ui/casino/cta-bg.png') }}"
                alt=""
                aria-hidden="true"
                class="bb-cta-background"
            >

            <div
                class="bb-cta-background-shade"
                aria-hidden="true"
            ></div>


            <div
                class="relative z-10 grid min-h-[300px]
                       items-center gap-8 px-6 py-9
                       md:px-10
                       lg:grid-cols-[0.72fr_1.55fr_0.73fr]
                       lg:px-12"
            >

                {{-- left empty composition area:
                     chips are already contained in the image --}}
                <div
                    class="hidden lg:block"
                    aria-hidden="true"
                ></div>


                {{-- ================================================= --}}
                {{-- REAL EXISTING CTA CONTENT                         --}}
                {{-- ================================================= --}}

                <div data-bb-reveal class="bb-cta-content" style="--bb-reveal-delay: 80ms">

                    @guest

                        <h2 class="bb-cta-title">
                            Join & Start Winning Today
                        </h2>

                        <p class="bb-cta-description">
                            Register now and get access to exclusive games and bonuses.
                        </p>

                        <a
                            href="/register"
                            class="bb-cta-button"
                        >

                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.9"
                                aria-hidden="true"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M20 21a8 8 0 0 0-16 0"
                                />

                                <circle cx="12" cy="7" r="4"/>
                            </svg>

                            <span>
                                Get Started
                            </span>

                            <span class="bb-cta-button-arrow">
                                →
                            </span>

                        </a>

                    @endguest



                    @auth

                        @if(auth()->user()->hasRole('player'))

                            <h2 class="bb-cta-title">
                                Start Winning Big!!!
                            </h2>

                            <p class="bb-cta-description">
                                Play now and win big from our exclusive games.
                            </p>

                            <a
                                href="/games"
                                class="bb-cta-button"
                            >

                                <svg
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.9"
                                    aria-hidden="true"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="m9 18 6-6-6-6"
                                    />
                                </svg>

                                <span>
                                    Play Now
                                </span>

                                <span class="bb-cta-button-arrow">
                                    →
                                </span>

                            </a>


                        @elseif(auth()->user()->hasRole('agent'))

                            <h2 class="bb-cta-title">
                                BrahmaBull Gaming Club
                            </h2>

                            <p class="bb-cta-description">
                                Go to Dashboard to manage your agent account.
                            </p>

                            <a
                                href="/agent"
                                class="bb-cta-button"
                            >

                                <span>
                                    Dashboard
                                </span>

                                <span class="bb-cta-button-arrow">
                                    →
                                </span>

                            </a>


                        @elseif(auth()->user()->hasRole('admin'))

                            <h2 class="bb-cta-title">
                                BrahmaBull Gaming Club
                            </h2>

                            <p class="bb-cta-description">
                                Go to Dashboard to manage your admin account.
                            </p>

                            <a
                                href="/admin"
                                class="bb-cta-button"
                            >

                                <span>
                                    Dashboard
                                </span>

                                <span class="bb-cta-button-arrow">
                                    →
                                </span>

                            </a>

                        @endif

                    @endauth

                </div>


                {{-- ================================================= --}}
                {{-- EXISTING SITE BENEFITS, USED DECORATIVELY         --}}
                {{-- ================================================= --}}

                <div data-bb-reveal="right" class="bb-cta-benefits" style="--bb-reveal-delay: 150ms">

                    <div class="bb-cta-benefit">

                        <span class="bb-cta-benefit-check">
                            ✓
                        </span>

                        <span>
                            EXCITING GAMES
                        </span>

                    </div>


                    <div class="bb-cta-benefit">

                        <span class="bb-cta-benefit-check">
                            ✓
                        </span>

                        <span>
                            AMAZING REWARDS
                        </span>

                    </div>


                    <div class="bb-cta-benefit">

                        <span class="bb-cta-benefit-check">
                            ✓
                        </span>

                        <span>
                            SECURE GAMING
                        </span>

                    </div>

                </div>

            </div>

        </div>

    </div>

</section>
