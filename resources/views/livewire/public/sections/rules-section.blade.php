@php
    $ruleSummaries = [
        ['number' => '01', 'title' => 'Account', 'copy' => 'Use your own player account and keep login and game credentials private.'],
        ['number' => '02', 'title' => 'Deposits', 'copy' => 'Choose an available payment option and submit the correct amount, wallet, and payment proof.'],
        ['number' => '03', 'title' => 'Brahma Balance', 'copy' => 'Use only your available balance; play requests require enough balance and staff verification.'],
        ['number' => '04', 'title' => 'Credentials', 'copy' => 'Use the game credentials assigned to your account and the configured Play action.'],
        ['number' => '05', 'title' => 'Withdrawals', 'copy' => 'Provide the correct game account, amount, payment method, and wallet or QR details.'],
        ['number' => '06', 'title' => 'Support', 'copy' => 'Use Player Support Chat for help and provide accurate request information.'],
    ];
@endphp

<section id="rules" class="relative overflow-hidden bg-slate-950 py-12 md:py-16">
    <div class="pointer-events-none absolute inset-x-0 top-1/3 h-72 bg-gradient-to-r from-transparent via-purple-700/10 to-transparent blur-3xl" aria-hidden="true"></div>

    <div class="relative mx-auto max-w-[1380px] px-5 md:px-7">
        <header data-bb-reveal class="mx-auto max-w-3xl text-center">
            <p class="text-xs font-black uppercase tracking-[0.3em] text-amber-300">Play With Confidence</p>
            <h2 class="mt-3 font-['Montserrat'] text-3xl font-black uppercase text-white sm:text-4xl">
                BrahmaBull <span class="text-purple-400">Rules</span>
            </h2>
            <p class="mx-auto mt-4 max-w-2xl text-sm leading-7 text-slate-400 md:text-base">
                Know the platform rules, keep your information accurate, and follow each verified player workflow.
            </p>
        </header>

        <div class="mt-9 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($ruleSummaries as $rule)
                <article
                    data-bb-reveal
                    style="--bb-reveal-delay: {{ $loop->index * 65 }}ms"
                    class="group rounded-2xl border border-purple-500/20 bg-slate-900/70 p-5 shadow-xl shadow-purple-950/10 backdrop-blur-xl transition duration-300 hover:-translate-y-1 hover:border-purple-400/45"
                >
                    <div class="flex items-start gap-4">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-amber-300/30 bg-amber-400/10 text-xs font-black text-amber-300">
                            {{ $rule['number'] }}
                        </span>
                        <div>
                            <h3 class="font-['Montserrat'] text-base font-black text-white">{{ $rule['title'] }}</h3>
                            <p class="mt-2 text-sm leading-6 text-slate-400">{{ $rule['copy'] }}</p>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>

        <div data-bb-reveal style="--bb-reveal-delay: 390ms" class="mt-9 text-center">
            <a
                href="{{ route('brahmabull-rules') }}"
                class="inline-flex min-h-12 items-center justify-center rounded-xl border border-purple-400/50 bg-gradient-to-r from-purple-600 to-indigo-600 px-7 py-3 text-sm font-black uppercase tracking-wider text-white shadow-lg shadow-purple-950/30 transition hover:border-purple-300 hover:shadow-purple-700/25"
            >
                View All Rules
            </a>
        </div>
    </div>
</section>
