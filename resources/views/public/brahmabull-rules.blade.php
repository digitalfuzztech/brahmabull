@component('layouts.public', [
    'title' => 'BrahmaBull Rules',
    'description' => 'Practical BrahmaBull account, deposit, balance, game access, cashout, Spin, and support rules for players.',
])
    @php
        $ruleSections = [
            [
                'number' => '01',
                'title' => 'Account & Access',
                'accent' => 'purple',
                'rules' => [
                    'Use your own player account and provide accurate account information.',
                    'Keep your account password and assigned game credentials private.',
                    'Use only the game credentials and access links provided for your account.',
                    'Contact Player Support Chat if you believe your account or credentials have been compromised.',
                ],
            ],
            [
                'number' => '02',
                'title' => 'Deposits',
                'accent' => 'blue',
                'rules' => [
                    'Select an available game, payment method, and wallet before sending payment.',
                    'Enter the correct amount and upload the required payment proof for the request.',
                    'A submitted deposit remains pending until an Admin or Agent verifies it.',
                    'Do not treat a pending or rejected submission as credited game funds or points.',
                ],
            ],
            [
                'number' => '03',
                'title' => 'Brahma Balance',
                'accent' => 'amber',
                'rules' => [
                    'Brahma Balance is updated through verified Brahma Deposits and applicable reward flows shown by the platform.',
                    'A Brahma Play request requires enough available balance when it is submitted and processed.',
                    'A successfully verified Brahma Play request debits the approved play amount through the existing balance workflow.',
                    'Wait for verification and the related notification before treating a request as completed.',
                ],
            ],
            [
                'number' => '04',
                'title' => 'Game Credentials',
                'accent' => 'purple',
                'rules' => [
                    'Game credentials are provided after successful processing where the selected workflow requires them.',
                    'Check Notifications for the assigned game username, password, loaded points, and available Play action.',
                    'Use the credentials assigned to your account and keep them private.',
                    'Open games through the configured Play link supplied by the platform.',
                ],
            ],
            [
                'number' => '05',
                'title' => 'Cashouts & Withdrawals',
                'accent' => 'blue',
                'rules' => [
                    'Select one of your available game accounts and enter the requested cashout amount accurately.',
                    'Choose a supported payment method and provide either a wallet address or cashtag, or upload a wallet QR image.',
                    'Cashout requests begin as pending and remain subject to the existing staff review workflow.',
                    'Follow platform notifications for updates to your request; submission does not guarantee approval or a specific processing time.',
                ],
            ],
            [
                'number' => '06',
                'title' => 'Promotions & Spin',
                'accent' => 'amber',
                'rules' => [
                    'Use the Spin Wheel only when the launcher shows that an attempt is available.',
                    'Spin availability depends on the platform’s current eligibility and verified-event rules.',
                    'The configured Spin system determines each result and applies the corresponding reward behavior.',
                    'No particular Spin reward, bonus, badge, or winning result is guaranteed.',
                ],
            ],
            [
                'number' => '07',
                'title' => 'Support & Conduct',
                'accent' => 'purple',
                'rules' => [
                    'Use Player Support Chat for help with your account or a pending request.',
                    'Keep support messages relevant and provide accurate details when a request must be checked.',
                    'Do not submit false payment information or attempt to access another player’s account or credentials.',
                    'Use the platform controls and request workflows as presented for your authenticated player account.',
                ],
            ],
        ];
    @endphp

    <div data-bb-reveal-page class="relative min-h-screen overflow-hidden bg-slate-950 px-5 py-14 text-slate-200 md:px-7 md:py-20">
        <div class="pointer-events-none absolute inset-x-0 top-0 h-96 bg-gradient-to-b from-purple-700/15 via-blue-700/5 to-transparent" aria-hidden="true"></div>
        <div class="pointer-events-none absolute -right-40 top-1/3 h-96 w-96 rounded-full bg-indigo-600/10 blur-3xl" aria-hidden="true"></div>

        <article class="relative mx-auto max-w-5xl">
            <header data-bb-reveal class="mb-10 text-center md:mb-14">
                <p class="text-xs font-black uppercase tracking-[0.3em] text-amber-300">Player Standards</p>
                <h1 class="mt-3 font-['Montserrat'] text-3xl font-black text-white sm:text-4xl md:text-5xl">BrahmaBull Rules</h1>
                <p class="mx-auto mt-4 max-w-2xl text-sm leading-7 text-slate-400 md:text-base">
                    Play responsibly. Follow the platform rules and keep your account and request information accurate.
                </p>
            </header>

            <div class="grid gap-5 md:grid-cols-2">
                @foreach($ruleSections as $section)
                    @php
                        $accentClasses = match ($section['accent']) {
                            'blue' => 'border-blue-400/25 bg-blue-500/10 text-blue-300',
                            'amber' => 'border-amber-300/25 bg-amber-400/10 text-amber-300',
                            default => 'border-purple-400/25 bg-purple-500/10 text-purple-300',
                        };
                    @endphp

                    <section data-bb-reveal style="--bb-reveal-delay: {{ min($loop->index, 5) * 75 }}ms" class="rounded-2xl border border-slate-700/70 bg-slate-900/75 p-6 shadow-xl shadow-purple-950/10 backdrop-blur-xl {{ $loop->last ? 'md:col-span-2' : '' }}">
                        <div class="flex items-center gap-4">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border text-xs font-black {{ $accentClasses }}">
                                {{ $section['number'] }}
                            </span>
                            <h2 class="font-['Montserrat'] text-xl font-black text-white">{{ $section['title'] }}</h2>
                        </div>

                        <ul class="mt-5 space-y-3">
                            @foreach($section['rules'] as $rule)
                                <li class="flex gap-3 text-sm leading-6 text-slate-300">
                                    <span class="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-purple-400" aria-hidden="true"></span>
                                    <span>{{ $rule }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endforeach
            </div>

            <aside data-bb-reveal class="mt-8 rounded-2xl border border-amber-300/20 bg-amber-400/5 p-5 text-center text-sm leading-6 text-slate-300">
                Need help with an account or request? Signed-in players can open Player Support Chat from the public site.
            </aside>
        </article>
    </div>
@endcomponent
