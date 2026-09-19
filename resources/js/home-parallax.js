let cleanupHomepageParallax = null;

const smoothstep = (value) => value * value * (3 - (2 * value));

export const initializeHomepageParallax = () => {
    cleanupHomepageParallax?.();
    cleanupHomepageParallax = null;

    const zone = document.querySelector('[data-bb-home-parallax]');
    const rules = zone?.querySelector('[data-bb-rules-parallax]');
    const cta = zone?.querySelector('[data-bb-parallax-cta]');
    const footer = document.querySelector('[data-bb-parallax-footer]');
    const motionQuery = window.matchMedia('(prefers-reduced-motion: reduce)');

    if (!zone || !rules || !cta || !footer || motionQuery.matches) return;

    const controller = new AbortController();
    let frame = null;
    let triggerScroll = 0;

    footer.classList.add('bb-footer-parallax-active');

    const measure = () => {
        const ctaRect = cta.getBoundingClientRect();
        triggerScroll = window.scrollY + ctaRect.top + (ctaRect.height / 2) - (window.innerHeight / 2);
    };

    const render = () => {
        frame = null;

        const width = window.innerWidth;
        const desktop = width >= 1024;
        const tablet = width >= 641 && width < 1024;
        const zoneRect = zone.getBoundingClientRect();
        const zoneProgress = Math.max(0, Math.min(zoneRect.height + window.innerHeight, window.innerHeight - zoneRect.top));
        const baseRulesRate = desktop ? 0.045 : (tablet ? 0.025 : 0.008);
        const baseRulesOffset = Math.min(desktop ? 42 : (tablet ? 24 : 8), zoneProgress * baseRulesRate);
        const extraRate = desktop ? 1 : (tablet ? 0.55 : 0.12);
        const rampDistance = desktop ? 140 : (tablet ? 110 : 80);
        const scrollPastTrigger = Math.max(0, window.scrollY - triggerScroll);
        const ramp = smoothstep(Math.min(1, scrollPastTrigger / rampDistance));
        const maxExtra = desktop ? Math.min(520, rules.offsetHeight * 0.65) : (tablet ? 260 : 60);
        const extraRulesOffset = Math.min(maxExtra, scrollPastTrigger * extraRate * ramp);
        const rulesOffset = -(baseRulesOffset + extraRulesOffset);

        const backgroundRate = desktop ? 0.1 : (tablet ? 0.06 : 0.025);
        const backgroundOffset = Math.min(desktop ? 96 : (tablet ? 44 : 10), zoneProgress * backgroundRate);

        const footerRect = footer.getBoundingClientRect();
        const footerEntry = Math.max(0, window.innerHeight - footerRect.top);
        const footerRate = desktop ? 0.06 : (tablet ? 0.04 : 0.02);
        const footerOffset = -Math.min(desktop ? 16 : (tablet ? 10 : 4), footerEntry * footerRate);

        zone.style.setProperty('--bb-rules-parallax-y', `${rulesOffset.toFixed(2)}px`);
        zone.style.setProperty('--bb-rules-bg-y', `${backgroundOffset.toFixed(2)}px`);
        footer.style.setProperty('--bb-footer-y', `${footerOffset.toFixed(2)}px`);
    };

    const requestRender = () => {
        if (frame === null) frame = window.requestAnimationFrame(render);
    };

    const remeasure = () => {
        measure();
        requestRender();
    };

    measure();
    render();
    window.addEventListener('scroll', requestRender, { passive: true, signal: controller.signal });
    window.addEventListener('resize', remeasure, { passive: true, signal: controller.signal });
    motionQuery.addEventListener('change', initializeHomepageParallax, { signal: controller.signal });

    cleanupHomepageParallax = () => {
        controller.abort();
        if (frame !== null) window.cancelAnimationFrame(frame);
        zone.style.removeProperty('--bb-rules-parallax-y');
        zone.style.removeProperty('--bb-rules-bg-y');
        footer.style.removeProperty('--bb-footer-y');
        footer.classList.remove('bb-footer-parallax-active');
    };
};

document.addEventListener('DOMContentLoaded', initializeHomepageParallax);
document.addEventListener('livewire:navigated', initializeHomepageParallax);
