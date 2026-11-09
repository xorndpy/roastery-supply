import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

// Uploader foto produk (admin): pratinjau, drag & drop, jadikan utama, hapus. File baru dikirim lewat <input name="images[]">.
Alpine.data('productImages', (initial = [], max = 5, maxMb = 2) => ({
    existing: initial,
    added: [],
    dragging: false,
    error: '',
    max,
    maxMb,
    get visibleExisting() { return this.existing.filter((i) => !i.removed); },
    get removedCount() { return this.existing.filter((i) => i.removed).length; },
    get total() { return this.visibleExisting.length + this.added.length; },
    pick(e) { this.addFiles(e.target.files); },
    drop(e) { this.dragging = false; this.addFiles(e.dataTransfer.files); },
    addFiles(list) {
        this.error = '';
        for (const f of Array.from(list)) {
            if (!/^image\/(jpeg|png|webp)$/.test(f.type)) { this.error = `"${f.name}" bukan JPG, PNG, atau WebP.`; continue; }
            if (f.size > this.maxMb * 1048576) { this.error = `"${f.name}" lebih dari ${this.maxMb} MB.`; continue; }
            if (this.total >= this.max) { this.error = `Maksimal ${this.max} foto per produk.`; break; }
            this.added.push({ file: f, url: URL.createObjectURL(f), name: f.name });
        }
        this.sync();
    },
    removeNew(i) { URL.revokeObjectURL(this.added[i].url); this.added.splice(i, 1); this.error = ''; this.sync(); },
    remove(img) { img.removed = true; this.error = ''; },
    restoreAll() {
        this.error = '';
        this.existing.forEach((i) => { if (i.removed && this.total < this.max) i.removed = false; });
    },
    makePrimary(img) {
        const i = this.existing.indexOf(img);
        this.existing.splice(i, 1);
        this.existing.unshift(img);
    },
    sync() {
        const dt = new DataTransfer();
        this.added.forEach((a) => dt.items.add(a.file));
        this.$refs.input.files = dt.files;
    },
}));

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
