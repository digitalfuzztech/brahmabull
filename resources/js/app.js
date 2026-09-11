import './bootstrap';
import './slider.js';

const initializeHomepageReveals = () => {
    const revealElements = document.querySelectorAll('[data-bb-reveal]');

    if (!document.querySelector('[data-bb-home-reveals], [data-bb-reveal-page]')) {
        revealElements.forEach((element) => {
            element.classList.remove('bb-reveal-pending', 'bb-reveal-visible');
            element.removeAttribute('data-bb-reveal-initialized');
        });

        return;
    }

    const pendingElements = [...revealElements].filter(
        (element) => !element.hasAttribute('data-bb-reveal-initialized'),
    );

    if (pendingElements.length === 0) return;

    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (reducedMotion || !('IntersectionObserver' in window)) {
        pendingElements.forEach((element) => {
            element.setAttribute('data-bb-reveal-initialized', 'true');
        });

        return;
    }

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) return;

            const element = entry.target;
            let finished = false;

            const finish = (event = null) => {
                if (event && event.target !== element) return;

                if (finished) return;

                finished = true;
                element.removeEventListener('transitionend', finish);
                element.classList.remove('bb-reveal-pending', 'bb-reveal-visible');
            };

            element.classList.remove('bb-reveal-pending');
            element.classList.add('bb-reveal-visible');
            element.addEventListener('transitionend', finish);
            window.setTimeout(finish, 1600);
            observer.unobserve(element);
        });
    }, {
        threshold: 0.14,
        rootMargin: '0px 0px -7% 0px',
    });

    try {
        pendingElements.forEach((element) => {
            element.setAttribute('data-bb-reveal-initialized', 'true');
            element.classList.add('bb-reveal-pending');
            observer.observe(element);
        });
    } catch {
        observer.disconnect();

        pendingElements.forEach((element) => {
            element.classList.remove('bb-reveal-pending', 'bb-reveal-visible');
        });
    }
};

document.addEventListener('DOMContentLoaded', initializeHomepageReveals);
document.addEventListener('livewire:navigated', initializeHomepageReveals);

const hidePreloader = () => document.getElementById('preloader')?.classList.add('hide');
const showPreloader = () => document.getElementById('preloader')?.classList.remove('hide');

/**
 * 1. ALWAYS hide when page fully loads
 */
window.addEventListener('load', () => {
    hidePreloader();
});

/**
 * 2. Show on normal link navigation
 */
document.addEventListener('click', (e) => {
    const a = e.target.closest('a');

    if (!a) return;

    const href = a.getAttribute('href');

    if (
        !href ||
        href.startsWith('#') ||
        href.startsWith('javascript:') ||
        a.target === '_blank'
    ) return;

    showPreloader();
});

/**
 * 3. CRITICAL FIX: Always hide on Livewire navigation / redirect
 */
document.addEventListener('livewire:navigated', () => {
    hidePreloader();
});

/**
 * 4. Safety fallback (prevents stuck loader after login)
 */
document.addEventListener('DOMContentLoaded', () => {
    setTimeout(() => {
        hidePreloader();
    }, 500);
});
