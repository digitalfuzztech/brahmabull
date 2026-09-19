<div data-bb-home-reveals class="min-h-screen bg-slate-950 text-white">

    @include('livewire.public.sections.hero-section')

    @include('livewire.public.sections.top-games-section')

    <livewire:public.top-winners />

    @include('livewire.public.sections.about-section')

    {{-- ========================================================= --}}
    {{-- RULES + CTA CASINO ENVIRONMENT                            --}}
    {{-- ========================================================= --}}

    <div class="bb-rules-cta-zone" data-bb-home-parallax>

        {{-- dark cinematic overlay --}}
        <div class="bb-rules-zone-shade" aria-hidden="true"></div>

        {{-- atmospheric glows --}}
        <div class="bb-rules-zone-glow bb-rules-zone-glow-1" aria-hidden="true"></div>
        <div class="bb-rules-zone-glow bb-rules-zone-glow-2" aria-hidden="true"></div>

        <div class="bb-rules-parallax-layer" data-bb-rules-parallax>
            @include('livewire.public.sections.rules-section')
        </div>

        <div class="bb-cta-parallax-layer" data-bb-parallax-cta>
            @include('livewire.public.sections.cta-section')
        </div>

    </div>


</div>
