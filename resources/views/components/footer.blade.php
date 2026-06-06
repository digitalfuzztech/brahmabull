<footer class="border-t border-slate-800 py-12">

    <div class="mx-auto max-w-7xl px-6">

        <div class="flex flex-col gap-4 md:flex-row md:justify-between items-center">
            <div class="flex flex-col items-center">
                <img src="{{ asset('images/logo-brahma.png') }}" class="w-[300px]">
            </div>

<div>
    @guest
        <h3 class="bold uppercase text-center mb-4">

            Hear From Us

        </h3>
        @endguest
    @auth
    @if(auth()->user()->hasRole('player'))
    <h3 class="bold uppercase text-center mb-4">

        Contact Us

    </h3>
    @elseif(auth()->user()->hasRole('agent'))
                <h3 class="bold uppercase text-center mb-4">

                    Contact Admin

                </h3>
        @elseif(auth()->user()->hasRole('admin'))

        @endif
    @endauth
        <livewire:public.footer-contact />
</div>


            <div class="flex flex-col items-center md:items-end  md:justify-end">
                <h3 class="font-black uppercase text-right">

                    BrahmaBull Gaming Club

                </h3>

                <p class="text-sm text-slate-400 text-center md:text-right">

                    Play. Win. Dominate.

                </p>
                <p class="text-sm text-slate-400 text-center md:text-right">
                    42 Homestead Drive
                    Far Rockaway, NY 11691
                </p>

            </div>




        </div>
        <div class="text-sm text-slate-500 text-center mt-10">

            © {{ date('Y') }} BrahmaBull Gaming Club.
            All rights reserved.

        </div>
    </div>

</footer>
