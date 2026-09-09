@props([
    'title' => 'Game',
    'desc' => 'Win real rewards',
    'image' => '/images/games/default.jpg'
])

<div class="bb-game-card group">

    {{-- GAME IMAGE --}}
    <img
        src="{{ $image }}"
        alt="{{ $title }}"
        class="bb-game-card-image"
    >


    {{-- SUBTLE TOP VIGNETTE --}}
    <div
        class="bb-game-card-top-shade"
        aria-hidden="true"
    ></div>


    {{-- DARK LOWER GRADIENT FOR TEXT --}}
    <div
        class="bb-game-card-bottom-shade"
        aria-hidden="true"
    ></div>


    {{-- HOVER LIGHT SWEEP --}}
    <div
        class="bb-game-card-shine"
        aria-hidden="true"
    ></div>


    {{-- GAME INFORMATION --}}
    <div class="bb-game-card-content">

        <h3 class="bb-game-card-title">
            {{ $title }}
        </h3>

        <p class="bb-game-card-description">
            {{ $desc }}
        </p>

    </div>


    {{-- INNER EDGE --}}
    <div
        class="bb-game-card-inner-edge"
        aria-hidden="true"
    ></div>

</div>
