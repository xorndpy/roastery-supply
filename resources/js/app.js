import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();

// Efek muncul saat di-scroll untuk elemen [data-reveal]. Tanpa JS atau dengan prefers-reduced-motion, konten langsung tampil.
(function () {
    const els = [...document.querySelectorAll('[data-reveal]')];
    if (!els.length || !('IntersectionObserver' in window) || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    const io = new IntersectionObserver((entries) => {
        entries.forEach((e) => {
            if (e.isIntersecting) { e.target.classList.add('is-in'); io.unobserve(e.target); }
        });
    }, { threshold: 0.12, rootMargin: '0px 0px -6% 0px' });

    document.documentElement.classList.add('js-reveal');
    els.forEach((el) => io.observe(el));
})();
