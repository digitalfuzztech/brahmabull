@component('layouts.public', ['title' => 'Guide To Play | BrahmaBull'])
    @php
        $steps = [
            ['title' => 'Create or access your account', 'copy' => 'Register for a player account or log in with your existing credentials. Player game, payment, notification, and support features require authentication.'],
            ['title' => 'Browse available games', 'copy' => 'Open the game catalog and choose an active game. Select Play to open the current funding options for that game.'],
            ['title' => 'Choose how to fund play', 'copy' => 'Use Pay For Game for a direct game deposit, or choose Use Brahma Balance to submit a Brahma Play request using available balance.'],
            ['title' => 'Submit a normal game deposit', 'copy' => 'Enter the deposit amount, select an available payment method and wallet, send payment using the shown wallet details, and upload the payment screenshot.'],
            ['title' => 'Add Brahma Balance', 'copy' => 'Open the Brahma Balance control, select an available wallet, enter the amount, and upload payment proof. Your displayed balance changes only after the request is verified.'],
            ['title' => 'Submit a Brahma Play request', 'copy' => 'From a game, choose Use Brahma Balance and enter the points to load. The request requires sufficient available Brahma Balance and remains pending until verification.'],
            ['title' => 'Wait for verification', 'copy' => 'An Admin or Agent reviews submitted payment or play details. Keep the generated reference number available if you need help with a request.'],
            ['title' => 'Receive game credentials', 'copy' => 'After a successful game deposit or Brahma Play verification, check Notifications for the game username, password, loaded points, and available Play action.'],
            ['title' => 'Open the game', 'copy' => 'Use the Play action supplied with the verified notification to open the configured game link, then sign in with the provided game credentials.'],
            ['title' => 'Request a cashout', 'copy' => 'Open Request Withdrawal, select your game and game account, enter the amount and payment method, then provide a wallet address or upload a QR image.'],
            ['title' => 'Follow notifications and support', 'copy' => 'Notifications report important request updates. Signed-in players can use Player Support Chat when they need help with an account or pending request.'],
            ['title' => 'Use eligible spins and rewards', 'copy' => 'When the Spin launcher shows available attempts, open it to use the current configured Spin experience. Rewards, bonuses, and badges follow the eligibility shown by the platform.'],
        ];
    @endphp

    <div data-bb-reveal-page class="relative min-h-screen overflow-hidden bg-slate-950 px-5 py-14 text-slate-200 md:px-7 md:py-20">
        <div class="pointer-events-none absolute inset-x-0 top-0 h-96 bg-gradient-to-b from-purple-700/15 via-blue-700/5 to-transparent" aria-hidden="true"></div>

        <article class="relative mx-auto max-w-5xl">
            <header data-bb-reveal class="mb-9 text-center md:mb-12">
                <p class="text-xs font-black uppercase tracking-[0.3em] text-amber-300">Player Guide</p>
                <h1 class="mt-3 font-['Montserrat'] text-3xl font-black text-white sm:text-4xl">Guide To Play</h1>
                <p class="mx-auto mt-4 max-w-2xl text-sm leading-7 text-slate-400">Follow the current BrahmaBull workflow from account access through verified game play and cashout requests.</p>
            </header>

            <ol class="grid gap-5 md:grid-cols-2">
                @foreach($steps as $step)
                    <li data-bb-reveal style="--bb-reveal-delay: {{ min($loop->index, 5) * 70 }}ms" class="relative rounded-2xl border border-purple-500/20 bg-slate-900/70 p-6 shadow-xl shadow-purple-950/10 backdrop-blur-xl">
                        <div class="flex items-start gap-4">
                            <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-amber-300/40 bg-amber-400/10 font-black text-amber-200">{{ $loop->iteration }}</span>
                            <div>
                                <h2 class="font-['Montserrat'] text-base font-black text-white">{{ $step['title'] }}</h2>
                                <p class="mt-2 text-sm leading-6 text-slate-300">{{ $step['copy'] }}</p>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ol>

            <div data-bb-reveal class="mt-10 text-center">
                @guest
                    <a href="{{ route('register') }}" class="inline-flex rounded-xl bg-gradient-to-r from-purple-600 to-blue-600 px-7 py-3 font-black text-white shadow-lg shadow-purple-950/30 transition hover:brightness-110">Create Player Account</a>
                @else
                    @if(auth()->user()->hasRole('player'))
                        <a href="{{ route('games') }}" class="inline-flex rounded-xl bg-gradient-to-r from-purple-600 to-blue-600 px-7 py-3 font-black text-white shadow-lg shadow-purple-950/30 transition hover:brightness-110">Browse Games</a>
                    @endif
                @endguest
            </div>
        </article>
    </div>
@endcomponent
