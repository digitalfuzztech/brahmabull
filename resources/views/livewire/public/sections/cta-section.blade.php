<section id="cta" class="py-24">

    <div class="mx-auto max-w-7xl px-6">

        <div class="rounded-3xl bg-gradient-to-r from-purple-700 to-indigo-700 p-16 text-center">



            @guest
                <h2 class="text-5xl font-black mb-6">
                    Join & Start Winning Today
                </h2>

                <p class="mb-8 text-lg">
                    Register now and get access to exclusive games and bonuses.
                </p>
                <a href="/register"
                   class="inline-block rounded-xl bg-white px-10 py-4 font-bold text-black">

                    Get Started

                </a>

            @endguest


            @auth

                @if(auth()->user()->hasRole('player'))
                        <h2 class="text-5xl font-black mb-6">
                            Start Winning Big!!!
                        </h2>

                        <p class="mb-8 text-lg">
                            Play now and win big from our exclusive games.
                        </p>
                    <a href="/games"
                       class="inline-block rounded-xl bg-white px-10 py-4 font-bold text-black">

                        Play Now

                    </a>

                @elseif(auth()->user()->hasRole('agent'))
                        <h2 class="text-5xl font-black mb-6">
                            BrahmaBull Gaming Club
                        </h2>

                        <p class="mb-8 text-lg">
                            Go to Dashboard to manage your agent account.
                        </p>
                    <a href="/agent"
                       class="inline-block rounded-xl bg-white px-10 py-4 font-bold text-black">

                        Dashboard

                    </a>

                @elseif(auth()->user()->hasRole('admin'))
                        <h2 class="text-5xl font-black mb-6">
                            BrahmaBull Gaming Club
                        </h2>

                        <p class="mb-8 text-lg">
                            Go to Dashboard to manage your admin account.
                        </p>
                    <a href="/admin"
                       class="inline-block rounded-xl bg-white px-10 py-4 font-bold text-black">

                        Dashboard

                    </a>

                @endif

            @endauth

        </div>

    </div>

</section>
