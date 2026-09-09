<footer class="bb-footer">

    {{-- faint casino atmosphere --}}
    <div
        class="bb-footer-background"
        aria-hidden="true"
    ></div>

    <div
        class="bb-footer-glow bb-footer-glow-left"
        aria-hidden="true"
    ></div>

    <div
        class="bb-footer-glow bb-footer-glow-right"
        aria-hidden="true"
    ></div>


    <div class="relative z-10 mx-auto max-w-[1180px] px-5 md:px-7">

        {{-- ===================================================== --}}
        {{-- MAIN FOOTER                                          --}}
        {{-- ===================================================== --}}

        <div class="bb-footer-main">

            {{-- ================================================= --}}
            {{-- LEFT: BRAND                                       --}}
            {{-- ================================================= --}}

            <div class="bb-footer-brand">

                <img
                    src="{{ asset('images/logo-brahma.png') }}"
                    alt="BrahmaBull Gaming Club"
                    class="bb-footer-logo"
                >

                <p class="bb-footer-tagline">
                    Play. Win. Dominate.
                </p>

            </div>


            {{-- ================================================= --}}
            {{-- CENTER: EXISTING CONTACT COMPONENT                --}}
            {{-- ================================================= --}}

            <div class="bb-footer-contact">

                @guest

                    <h3 class="bb-footer-heading">
                        Stay Connected
                    </h3>

                @endguest


                @auth

                    @if(auth()->user()->hasRole('player'))

                        <h3 class="bb-footer-heading">
                            Contact Us
                        </h3>

                    @elseif(auth()->user()->hasRole('agent'))

                        <h3 class="bb-footer-heading">
                            Contact Admin
                        </h3>

                    @elseif(auth()->user()->hasRole('admin'))

                    @endif

                @endauth


                <livewire:public.footer-contact />

            </div>


            {{-- ================================================= --}}
            {{-- RIGHT: EXISTING COMPANY DETAILS                   --}}
            {{-- ================================================= --}}

            <div class="bb-footer-company">

                <h3 class="bb-footer-company-name">
                    BrahmaBull Gaming Club
                </h3>

                <p class="bb-footer-company-tagline">
                    Play. Win. Dominate.
                </p>


                <div class="bb-footer-address">

                    <span class="bb-footer-location-icon">

                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            aria-hidden="true"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"
                            />

                            <circle
                                cx="12"
                                cy="10"
                                r="2.5"
                            />
                        </svg>

                    </span>

                    <p>
                        42 Homestead Drive<br>
                        Far Rockaway, NY 11691
                    </p>

                </div>

            </div>

        </div>


        {{-- ===================================================== --}}
        {{-- BOTTOM BAR                                           --}}
        {{-- ===================================================== --}}

        <div class="bb-footer-bottom">

            <div class="bb-footer-bottom-line"></div>

            <div class="bb-footer-copyright">

                © {{ date('Y') }} BrahmaBull Gaming Club.
                All rights reserved.

            </div>

        </div>

    </div>

</footer>
