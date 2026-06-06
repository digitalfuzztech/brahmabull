<section id="games" class="py-24">

    <div class="mx-auto max-w-7xl px-6">

        <!-- HEADER -->
        <div class="mb-10 flex items-center justify-between">

            <h2 class="text-4xl font-black">
                Top Games
            </h2>
            @guest
            <a href="/login" class="text-purple-400 hover:text-purple-300">
                See All →
            </a>
            @endguest
            @auth
            @if(auth()->user()->hasRole('player'))
            <a href="{{route('games')}}" class="text-purple-400 hover:text-purple-300">
                See All →
            </a>
            @endif
                @endauth
        </div>

        <!-- OUTER WRAPPER -->
        <div
            x-data="dragScroll()"
            x-init="init()"
            class="relative overflow-hidden"
        >

            <!-- LEFT FADE -->
            <div class="pointer-events-none absolute left-0 top-0 z-10 h-full w-20 bg-gradient-to-r from-slate-950 to-transparent"></div>

            <!-- RIGHT FADE -->
            <div class="pointer-events-none absolute right-0 top-0 z-10 h-full w-20 bg-gradient-to-l from-slate-950 to-transparent"></div>

            <!-- SCROLL ROW -->
            <div
                x-ref="slider"
                class="flex gap-6 cursor-grab active:cursor-grabbing select-none overflow-x-scroll scroll- scrollbar-hide pb-4"
            >

                @foreach($games as $game)

                    <div class="min-w-[260px]">

                        @guest
                            <a href="{{ route('login') }}" class="block">
                                <x-game-card
                                    :title="$game->name"
                                    :desc="$game->description ?? 'Play now'"
                                    :image="asset('storage/' . $game->image)"
                                />
                            </a>
                        @endguest

                        @auth
                            @if(auth()->user()->hasRole('player'))
                                <a href="{{ route('games') }}" class="block">
                                    <x-game-card
                                        :title="$game->name"
                                        :desc="$game->description ?? 'Play now'"
                                        :image="asset('storage/' . $game->image)"
                                    />
                                </a>
                            @else
                                <x-game-card
                                    :title="$game->name"
                                    :desc="$game->description ?? 'Play now'"
                                    :image="asset('storage/' . $game->image)"
                                />
                            @endif
                        @endauth

                    </div>

                @endforeach

            </div>

        </div>

    </div>
    <script>
        function dragScroll() {
            return {
                isDown: false,
                startX: 0,
                scrollLeft: 0,

                init() {
                    const slider = this.$refs.slider;

                    slider.addEventListener('mousedown', (e) => {
                        this.isDown = true;
                        slider.classList.add('cursor-grabbing');
                        this.startX = e.pageX - slider.offsetLeft;
                        this.scrollLeft = slider.scrollLeft;
                    });

                    slider.addEventListener('mouseleave', () => {
                        this.isDown = false;
                    });

                    slider.addEventListener('mouseup', () => {
                        this.isDown = false;
                    });

                    slider.addEventListener('mousemove', (e) => {
                        if (!this.isDown) return;
                        e.preventDefault();

                        const x = e.pageX - slider.offsetLeft;
                        const walk = (x - this.startX) * 2; // speed multiplier

                        slider.scrollLeft = this.scrollLeft - walk;
                    });

                    // Touch support
                    let startXTouch = 0;
                    let scrollStartTouch = 0;

                    slider.addEventListener('touchstart', (e) => {
                        startXTouch = e.touches[0].pageX;
                        scrollStartTouch = slider.scrollLeft;
                    });

                    slider.addEventListener('touchmove', (e) => {
                        const x = e.touches[0].pageX;
                        const walk = (startXTouch - x) * 2;
                        slider.scrollLeft = scrollStartTouch + walk;
                    });
                }
            }
        }
    </script>
</section>
