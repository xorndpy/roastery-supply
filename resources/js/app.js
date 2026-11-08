import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

// Drawer filter katalog: fokus masuk saat dibuka, terkunci di dalam panel (Tab), kembali ke tombol saat ditutup.
Alpine.data('filterDrawer', () => ({
    filters: false,
    open() {
        this.filters = true;
        document.body.classList.add('overflow-hidden');
        this.$nextTick(() => this.$refs.panel.querySelector('select, input')?.focus());
    },
    close() {
        this.filters = false;
        document.body.classList.remove('overflow-hidden');
        this.$refs.trigger?.focus();
    },
    trap(e) {
        const f = [...this.$refs.panel.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]), select:not([disabled])')]
            .filter((el) => el.offsetParent !== null);
        if (!f.length) return;
        const first = f[0], last = f[f.length - 1];
        if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
        else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    },
}));

Alpine.start();

// Efek muncul saat di-scroll untuk elemen [data-reveal]. Tanpa JS atau dengan prefers-reduced-motion, konten langsung tampil.
(function () {
    const els = [...document.querySelectorAll('[data-reveal], [data-draw]')];
    if (!els.length || !('IntersectionObserver' in window) || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    const io = new IntersectionObserver((entries) => {
        entries.forEach((e) => {
            if (e.isIntersecting) { e.target.classList.add('is-in'); io.unobserve(e.target); }
        });
    }, { threshold: 0.12, rootMargin: '0px 0px -6% 0px' });

    document.documentElement.classList.add('js-reveal');
    els.forEach((el) => io.observe(el));
})();
