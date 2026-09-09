<section
    id="games"
    class="bb-top-games-section relative overflow-hidden py-14 md:py-16 relative"
>
    <img src="{{asset('images/ui/casino/top-games-bg.png')}}" alt="" class="bg-top-games">
    <div class="relative z-10 mx-auto max-w-[1180px] px-5 md:px-7">

        {{-- ===================================================== --}}
        {{-- SECTION HEADER                                        --}}
        {{-- ===================================================== --}}

        <div class="mb-7 flex items-center flex-col gap-5 md:mb-8">

            <div class="flex gap-3 flex-col justify-center items-center">


                <div class="flex gap-3 items-center">
                    <img
                        src="{{ asset('images/ui/casino/top-games-fire.png') }}"
                        alt=""
                        aria-hidden="true"
                        class="bb-top-games-fire"
                    >

                    <h2 class="bb-display text-[2rem] font-black tracking-[-0.035em] text-white md:text-[2.35rem]">
                        Top Games
                    </h2>
                    <img
                        src="{{ asset('images/ui/casino/top-games-fire.png') }}"
                        alt=""
                        aria-hidden="true"
                        class="bb-top-games-fire"
                    >

                </div>
                <p class="text-[14px] md:text-[16px]" >
                    Play the most amazing games we have and start winning today
                </p>



            </div>


            @guest
                <a
                    href="/login"
                    class="bb-top-games-see-all whitespace-nowrap text-sm font-bold text-fuchsia-300"
                >
                    See All →
                </a>
            @endguest


            @auth
                @if(auth()->user()->hasRole('player'))

                    <a
                        href="{{ route('games') }}"
                        class="bb-top-games-see-all whitespace-nowrap text-sm font-bold text-fuchsia-300"
                    >
                        See All →
                    </a>

                @endif
            @endauth

        </div>

        {{-- ===================================================== --}}
        {{-- EXISTING DRAG SLIDER                                  --}}
        {{-- ===================================================== --}}

        <div
            x-data="dragScroll()"
            x-init="init()"
            class="bb-games-slider relative"
        >

            {{-- ===================================================== --}}
            {{-- SLIDER ROW                                            --}}
            {{-- ===================================================== --}}

            <div
                x-ref="slider"
                @scroll.passive="updateState()"
                class="bb-top-games-track flex cursor-grab select-none
               active:cursor-grabbing"
            >

                @foreach($games as $game)

                    <div class="bb-game-slide">

                        @guest

                            <a
                                href="{{ route('login') }}"
                                class="block h-full"
                            >
                                <x-game-card
                                    :title="$game->name"
                                    :desc="$game->description ?? 'Play now'"
                                    :image="asset('storage/' . $game->image)"
                                />
                            </a>

                        @endguest


                        @auth

                            @if(auth()->user()->hasRole('player'))

                                <a
                                    href="{{ route('games') }}"
                                    class="block h-full"
                                >
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


            {{-- ===================================================== --}}
            {{-- SLIDER CONTROLS                                       --}}
            {{-- ===================================================== --}}

            <div class="bb-games-slider-footer">

                <div class="bb-games-slider-hint">

                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                        class="h-4 w-4"
                        aria-hidden="true"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M8 7 3 12l5 5M16 7l5 5-5 5M4 12h16"
                        />
                    </svg>

                    <span>
            </span>

                </div>


                <div class="bb-games-slider-progress">

                    <div
                        class="bb-games-slider-progress-fill"
                        :style="`width: ${progress}%`"
                    ></div>

                </div>


                <div class="bb-games-slider-buttons">

                    <button
                        type="button"
                        @click="previous()"
                        :disabled="!canScrollLeft"
                        :class="{ 'bb-slider-button-disabled': !canScrollLeft }"
                        class="bb-games-slider-button"
                        aria-label="Previous games"
                    >
                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            class="h-4 w-4"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="m15 18-6-6 6-6"
                            />
                        </svg>
                    </button>


                    <button
                        type="button"
                        @click="next()"
                        :disabled="!canScrollRight"
                        :class="{ 'bb-slider-button-disabled': !canScrollRight }"
                        class="bb-games-slider-button"
                        aria-label="Next games"
                    >
                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            class="h-4 w-4"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="m9 18 6-6-6-6"
                            />
                        </svg>
                    </button>

                </div>

            </div>

        </div>

    </div>

    <script>
        function dragScroll() {
            return {
                isDown: false,
                startX: 0,
                scrollLeft: 0,

                canScrollLeft: false,
                canScrollRight: false,

                progress: 0,


                init() {
                    const slider = this.$refs.slider;


                    const refresh = () => {
                        this.updateState();
                    };


                    this.$nextTick(() => {
                        refresh();
                    });


                    window.addEventListener('resize', refresh);


                    slider.addEventListener('mousedown', (e) => {
                        this.isDown = true;

                        slider.classList.add('cursor-grabbing');

                        this.startX =
                            e.pageX - slider.offsetLeft;

                        this.scrollLeft =
                            slider.scrollLeft;
                    });


                    slider.addEventListener('mouseleave', () => {
                        this.isDown = false;

                        slider.classList.remove('cursor-grabbing');
                    });


                    slider.addEventListener('mouseup', () => {
                        this.isDown = false;

                        slider.classList.remove('cursor-grabbing');
                    });


                    slider.addEventListener('mousemove', (e) => {
                        if (!this.isDown) {
                            return;
                        }

                        e.preventDefault();

                        const x =
                            e.pageX - slider.offsetLeft;

                        const walk =
                            (x - this.startX) * 2;

                        slider.scrollLeft =
                            this.scrollLeft - walk;
                    });


                    /* Touch support */

                    let startXTouch = 0;
                    let scrollStartTouch = 0;


                    slider.addEventListener(
                        'touchstart',
                        (e) => {
                            startXTouch =
                                e.touches[0].pageX;

                            scrollStartTouch =
                                slider.scrollLeft;
                        },
                        {
                            passive: true
                        }
                    );


                    slider.addEventListener(
                        'touchmove',
                        (e) => {
                            const x =
                                e.touches[0].pageX;

                            const walk =
                                (startXTouch - x) * 2;

                            slider.scrollLeft =
                                scrollStartTouch + walk;
                        },
                        {
                            passive: true
                        }
                    );
                },


                updateState() {
                    const slider =
                        this.$refs.slider;

                    if (!slider) {
                        return;
                    }


                    const maxScroll =
                        Math.max(
                            slider.scrollWidth -
                            slider.clientWidth,
                            0
                        );


                    this.canScrollLeft =
                        slider.scrollLeft > 4;


                    this.canScrollRight =
                        slider.scrollLeft <
                        maxScroll - 4;


                    if (maxScroll <= 0) {
                        this.progress = 100;

                        return;
                    }


                    this.progress =
                        Math.min(
                            100,
                            Math.max(
                                8,
                                (
                                    slider.scrollLeft /
                                    maxScroll
                                ) * 100
                            )
                        );
                },


                previous() {
                    const slider =
                        this.$refs.slider;

                    slider.scrollBy({
                        left:
                            -(slider.clientWidth * 0.82),

                        behavior:
                            'smooth'
                    });
                },


                next() {
                    const slider =
                        this.$refs.slider;

                    slider.scrollBy({
                        left:
                            slider.clientWidth * 0.82,

                        behavior:
                            'smooth'
                    });
                }
            }
        }
    </script>
</section>
