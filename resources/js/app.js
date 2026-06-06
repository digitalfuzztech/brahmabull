import './bootstrap';
import './slider.js';

const preloader = document.getElementById('preloader');

/**
 * 1. ALWAYS hide when page fully loads
 */
window.addEventListener('load', () => {
    preloader?.classList.add('hide');
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

    preloader?.classList.remove('hide');
});

/**
 * 3. CRITICAL FIX: Always hide on Livewire navigation / redirect
 */
document.addEventListener('livewire:navigated', () => {
    preloader?.classList.add('hide');
});

/**
 * 4. Safety fallback (prevents stuck loader after login)
 */
document.addEventListener('DOMContentLoaded', () => {
    setTimeout(() => {
        preloader?.classList.add('hide');
    }, 500);
});
