@props(['siteSettings' => null])

@php
    $siteSettings ??= \App\Models\SiteSetting::current();
@endphp

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

            <div data-bb-reveal class="bb-footer-brand">

                <img
                    src="{{ $siteSettings->logoUrl('images/logo-brahma.png') }}"
                    alt="{{ $siteSettings->site_name }}"
                    class="bb-footer-logo"
                >

                <p class="bb-footer-tagline">
                    Play. Win. Dominate.
                </p>

            </div>


            {{-- ================================================= --}}
            {{-- CENTER: EXISTING CONTACT COMPONENT                --}}
            {{-- ================================================= --}}

            <div data-bb-reveal class="bb-footer-contact" style="--bb-reveal-delay: 90ms">

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

            <div data-bb-reveal class="bb-footer-company" style="--bb-reveal-delay: 180ms">

                <h3 class="bb-footer-company-name">
                    {{ $siteSettings->site_name }}
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

            @php
                $socialLinks = collect($siteSettings->socialLinks());
            @endphp

            @if($socialLinks->isNotEmpty())
                <nav class="mb-5 flex flex-wrap items-center justify-center gap-3" aria-label="BrahmaBull social media">
                    @foreach($socialLinks as $platform => $url)
                        @php
                            $label = match($platform) {
                                'facebook' => 'Facebook',
                                'instagram' => 'Instagram',
                                'x' => 'X',
                                'youtube' => 'YouTube',
                                'discord' => 'Discord',
                            };
                        @endphp
                        <a
                            href="{{ $url }}"
                            target="_blank"
                            rel="noopener noreferrer"
                            aria-label="Visit BrahmaBull on {{ $label }}"
                            title="{{ $label }}"
                            class="inline-flex h-11 w-11 items-center justify-center rounded-full border border-purple-400/30 bg-slate-950/70 text-slate-300 shadow-lg shadow-purple-950/20 transition hover:-translate-y-0.5 hover:border-blue-300/70 hover:text-white hover:shadow-[0_0_20px_rgba(99,102,241,0.35)] focus:outline-none focus:ring-2 focus:ring-purple-400"
                        >
                            @switch($platform)
                                @case('facebook')
                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M22 12.06C22 6.5 17.52 2 12 2S2 6.5 2 12.06C2 17.08 5.66 21.25 10.44 22v-7.03H7.9v-2.91h2.54V9.85c0-2.52 1.49-3.91 3.77-3.91 1.09 0 2.23.2 2.23.2v2.46h-1.25c-1.24 0-1.63.77-1.63 1.56v1.9h2.77l-.44 2.91h-2.33V22C18.34 21.25 22 17.08 22 12.06Z"/></svg>
                                    @break
                                @case('instagram')
                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1" fill="currentColor" stroke="none"/></svg>
                                    @break
                                @case('x')
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M18.9 2H22l-6.78 7.75L23.2 22h-6.25l-4.9-6.4L6.46 22H3.35l7.25-8.29L2.95 2H9.36l4.42 5.84L18.9 2Zm-1.1 17.84h1.72L8.42 4.05H6.57L17.8 19.84Z"/></svg>
                                    @break
                                @case('youtube')
                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M23.5 6.2a3 3 0 0 0-2.1-2.13C19.55 3.56 12 3.56 12 3.56s-7.55 0-9.4.51A3 3 0 0 0 .5 6.2 31.3 31.3 0 0 0 0 12a31.3 31.3 0 0 0 .5 5.8 3 3 0 0 0 2.1 2.13c1.85.51 9.4.51 9.4.51s7.55 0 9.4-.51a3 3 0 0 0 2.1-2.13A31.3 31.3 0 0 0 24 12a31.3 31.3 0 0 0-.5-5.8ZM9.6 15.61V8.39L15.86 12 9.6 15.61Z"/></svg>
                                    @break
                                @case('discord')
                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M19.54 5.34A16.5 16.5 0 0 0 15.44 4l-.5 1.02a15.3 15.3 0 0 0-5.87 0L8.56 4a16.7 16.7 0 0 0-4.1 1.35C1.87 9.2 1.17 12.95 1.52 16.64a16.8 16.8 0 0 0 5.03 2.55l1.23-1.68a10.8 10.8 0 0 1-1.94-.94l.48-.37c3.74 1.74 7.8 1.74 11.5 0l.48.37c-.62.37-1.27.68-1.95.94l1.23 1.68a16.7 16.7 0 0 0 5.03-2.55c.42-4.27-.72-7.98-3.07-11.3ZM8.73 14.38c-1.13 0-2.05-1.05-2.05-2.34s.9-2.34 2.05-2.34c1.14 0 2.07 1.06 2.05 2.34 0 1.29-.91 2.34-2.05 2.34Zm6.54 0c-1.13 0-2.05-1.05-2.05-2.34s.9-2.34 2.05-2.34c1.14 0 2.07 1.06 2.05 2.34 0 1.29-.9 2.34-2.05 2.34Z"/></svg>
                                    @break
                            @endswitch
                        </a>
                    @endforeach
                </nav>
            @endif

            <nav class="mb-5 flex flex-wrap items-center justify-center gap-x-6 gap-y-3 text-center text-xs font-bold text-slate-400" aria-label="BrahmaBull information">
                <a href="{{ route('privacy-policy') }}" class="cursor-pointer transition hover:text-purple-300">Privacy Policy</a>
                <a href="{{ route('terms-and-conditions') }}" class="cursor-pointer transition hover:text-purple-300">Terms and Conditions</a>
                <a href="{{ route('guide-to-play') }}" class="cursor-pointer transition hover:text-purple-300">Guide To Play</a>
            </nav>

            <div class="bb-footer-bottom-line"></div>

            <div class="bb-footer-copyright">

                © {{ date('Y') }} {{ $siteSettings->site_name }}.
                All rights reserved.

            </div>

        </div>

    </div>

</footer>
