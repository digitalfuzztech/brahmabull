<div data-bb-home-reveals class="min-h-screen bg-slate-950 text-white">

    @include('livewire.public.sections.hero-section')

    @include('livewire.public.sections.top-games-section')

    <livewire:public.top-winners />

    @include('livewire.public.sections.about-section')

    {{-- ========================================================= --}}
    {{-- RULES + CTA CASINO ENVIRONMENT                            --}}
    {{-- ========================================================= --}}

    <div class="bb-rules-cta-zone">

        {{-- dark cinematic overlay --}}
        <div class="bb-rules-zone-shade" aria-hidden="true"></div>

        {{-- atmospheric glows --}}
        <div class="bb-rules-zone-glow bb-rules-zone-glow-1" aria-hidden="true"></div>
        <div class="bb-rules-zone-glow bb-rules-zone-glow-2" aria-hidden="true"></div>

        <div class="relative z-10">
            @include('livewire.public.sections.rules-section')

            @include('livewire.public.sections.cta-section')
        </div>

    </div>


</div>
