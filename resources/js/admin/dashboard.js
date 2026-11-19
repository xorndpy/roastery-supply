/**
 * JavaScript halaman Dashboard admin (sebelumnya inline di dashboard.blade.php).
 * Dimuat hanya di halaman dashboard lewat import dinamis di app.js, jadi halaman lain tidak ikut menanggung ukurannya.
 */

/* ---------- baris tabel bisa diklik penuh ---------- */
function initClickableRows() {
    document.querySelectorAll('.dash-table tr[data-href]').forEach(tr => tr.addEventListener('click', e => { if (!e.target.closest('a')) window.location.href = tr.dataset.href; }));
}

/* ---------- pemilih periode (kalender rentang tanggal, Alpine) ---------- */
function tdRange(cfg) {
    const pad = n => String(n).padStart(2, '0');
    const iso = d => d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
    const parse = s => { const [y, m, d] = s.split('-').map(Number); return new Date(y, m - 1, d); };
    const fmtMonth = new Intl.DateTimeFormat('id-ID', { month: 'long', year: 'numeric' });
    const fmtShort = new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
    const fmtNoYear = new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'short' });
    const fmtLong = new Intl.DateTimeFormat('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
    const t = parse(cfg.today), end0 = parse(cfg.to);
    return {
        open: false, today: cfg.today, preset: cfg.preset, y: end0.getFullYear(), m: end0.getMonth() + 1,
        a: cfg.from, b: cfg.to, hover: null, days: {}, loaded: {},
        weekdays: ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'],
        presets: [
            { k: 'today', l: 'Hari ini' }, { k: '7', l: '7 hari terakhir' }, { k: '30', l: '30 hari terakhir' },
            { k: '90', l: '90 hari terakhir' }, { k: 'month', l: 'Bulan ini' }, { k: 'lastmonth', l: 'Bulan lalu' },
        ],
        get monthLabel() { return fmtMonth.format(new Date(this.y, this.m - 1, 1)); },
        get canNext() { return this.y < t.getFullYear() || (this.y === t.getFullYear() && this.m < t.getMonth() + 1); },
        get end() { return this.b || this.hover; },
        get lo() { if (!this.a) return null; const e = this.end; return e && e < this.a ? e : this.a; },
        get hi() { if (!this.a) return null; const e = this.end; return e && e > this.a ? e : this.a; },
        get summary() {
            if (!this.a) return 'Pilih tanggal awal';
            if (!this.b) return fmtShort.format(parse(this.a)) + ' → pilih tanggal akhir';
            if (this.a === this.b) return fmtShort.format(parse(this.a));
            const sameYear = this.a.slice(0, 4) === this.b.slice(0, 4);
            return (sameYear ? fmtNoYear : fmtShort).format(parse(this.a)) + ' – ' + fmtShort.format(parse(this.b));
        },
        get cells() {
            const first = new Date(this.y, this.m - 1, 1), lead = (first.getDay() + 6) % 7, dim = new Date(this.y, this.m, 0).getDate(), out = [];
            for (let i = 0; i < lead; i++) out.push({ date: 'x' + i, day: '', inMonth: false });
            for (let d = 1; d <= dim; d++) out.push({ date: this.y + '-' + pad(this.m) + '-' + pad(d), day: d, inMonth: true });
            return out;
        },
        cls(c) {
            if (!c.inMonth) return 'is-out';
            const r = [], lo = this.lo, hi = this.hi;
            if (lo && c.date === lo) r.push('is-start'); if (hi && c.date === hi) r.push('is-end');
            if (lo && hi && c.date > lo && c.date < hi) r.push('is-in');
            if (c.date === this.today) r.push('is-today');
            return r.join(' ');
        },
        label(c) { if (!c.inMonth) return ''; const n = this.days[c.date]; return fmtLong.format(parse(c.date)) + (n ? ', ' + n + ' pesanan' : ''); },
        toggle() { this.open ? this.close() : this.show(); },
        show() { this.a = cfg.from; this.b = cfg.to; this.y = end0.getFullYear(); this.m = end0.getMonth() + 1; this.open = true; this.load(); },
        close() { this.open = false; this.hover = null; },
        shift(d) { const n = new Date(this.y, this.m - 1 + d, 1); this.y = n.getFullYear(); this.m = n.getMonth() + 1; this.load(); },
        click(c) {
            if (!c.inMonth || c.date > this.today) return;
            if (!this.a || this.b) { this.a = c.date; this.b = null; }
            else if (c.date < this.a) { this.b = this.a; this.a = c.date; }
            else this.b = c.date;
        },
        go(params) { window.location.href = cfg.base + '?' + new URLSearchParams(params).toString(); },
        pick(k) { this.go({ range: k }); },
        apply() { if (this.a) this.go({ from: this.a, to: this.b || this.a }); },
        async load() {
            const month = this.y + '-' + pad(this.m);
            if (!cfg.cal || this.loaded[month]) return;
            try {
                const res = await fetch(cfg.cal + '?month=' + month, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
                if (!res.ok) return;
                const json = await res.json();
                Object.entries(json.days || {}).forEach(([d, rows]) => { this.days[d] = rows.length; });
                this.loaded[month] = true;
            } catch (e) { /* titik penanda pesanan bersifat tambahan; abaikan bila gagal */ }
        },
    };
}

/* ---------- grafik batang penjualan (SVG buatan sendiri) ---------- */
function initSalesChart() {
    const host = document.getElementById('tdChart'); if (!host) return;
    const live = document.getElementById('tdChartLive');
    const nf = new Intl.NumberFormat('id-ID');
    const rp = v => 'Rp ' + nf.format(Math.round(v));
    const num1 = x => String(Number(x.toFixed(1))).replace('.', ',');
    const short = v => v >= 1e9 ? num1(v / 1e9) + ' M' : v >= 1e6 ? num1(v / 1e6) + ' jt' : v >= 1e3 ? num1(v / 1e3) + ' rb' : String(v);
    const dates = JSON.parse(host.dataset.dates), today = host.dataset.today;
    const series = { ord: JSON.parse(host.dataset.ord).map(Number) };
    if (host.dataset.rev) series.rev = JSON.parse(host.dataset.rev).map(Number);
    const N = dates.length;
    const parse = s => { const [y, m, d] = s.split('-').map(Number); return new Date(y, m - 1, d); };
    const dShort = new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'short' });
    const dLong = new Intl.DateTimeFormat('id-ID', { weekday: 'long', day: 'numeric', month: 'long' });
    const ticks = m => { if (m <= 0) return [0, 1]; const raw = m / 4, mag = Math.pow(10, Math.floor(Math.log10(raw))); const step = [1, 2, 2.5, 5, 10].map(k => k * mag).find(s => s >= raw); const n = Math.ceil(m / step - 1e-9); return Array.from({ length: n + 1 }, (_, i) => i * step); };

    let metric = series.rev ? 'rev' : 'ord', geo = null, active = null, tip = null;
    const fmt = v => metric === 'rev' ? rp(v) : nf.format(v) + ' pesanan';
    const axis = v => metric === 'rev' ? short(v) : String(v);

    function stats() {
        const vals = series[metric], total = vals.reduce((a, b) => a + b, 0);
        const done = N > 1 ? vals.slice(0, -1) : vals, avg = done.reduce((a, b) => a + b, 0) / Math.max(1, done.length);
        const max = Math.max(0, ...vals), best = vals.indexOf(max);
        document.getElementById('tdStatTotalL').textContent = metric === 'rev' ? 'Total pendapatan' : 'Total pesanan';
        document.getElementById('tdStatTotal').textContent = metric === 'rev' ? rp(total) : nf.format(total);
        document.getElementById('tdStatAvg').textContent = metric === 'rev' ? rp(avg) : num1(avg);
        document.getElementById('tdStatBest').textContent = max > 0 ? dShort.format(parse(dates[best])) + ' · ' + (metric === 'rev' ? short(max) : nf.format(max)) : '–';
    }

    function build() {
        const vals = series[metric], total = vals.reduce((a, b) => a + b, 0), max = Math.max(0, ...vals), best = vals.indexOf(max);
        const W = host.clientWidth, H = host.clientHeight; if (!W) return;
        const pad = { l: metric === 'rev' ? 52 : 34, r: 6, t: 22, b: 26 }, iw = W - pad.l - pad.r, ih = H - pad.t - pad.b;
        const tk = ticks(max), top = tk[tk.length - 1], slot = iw / N, bw = Math.max(3, Math.min(40, slot * 0.62));
        const xc = i => pad.l + slot * i + slot / 2, y = v => pad.t + ih - (v / top) * ih;
        geo = { W, H, pad, slot, xc, y, N };

        let s = `<svg width="${W}" height="${H}" viewBox="0 0 ${W} ${H}" aria-hidden="true" focusable="false">`;
        (total === 0 ? [0] : tk).forEach(v => { const yy = y(v); s += `<line class="${v === 0 ? 'gb' : 'g'}" x1="${pad.l}" x2="${W - pad.r}" y1="${yy}" y2="${yy}"${total === 0 ? ' stroke-dasharray="3 5"' : ''}/>` + (total === 0 ? '' : `<text x="${pad.l - 8}" y="${yy + 4}" text-anchor="end">${axis(v)}</text>`); });
        const every = Math.max(1, Math.ceil(N / Math.max(2, Math.floor(iw / 62))));
        for (let i = 0; i < N; i++) if ((N - 1 - i) % every === 0) s += `<text x="${xc(i)}" y="${H - 6}" text-anchor="middle">${dShort.format(parse(dates[i]))}</text>`;
        vals.forEach((v, i) => {
            if (v <= 0) return;
            const h = Math.max(2, (v / top) * ih), x0 = xc(i) - bw / 2, y0 = y(0), yt = y0 - h, r = Math.min(6, bw / 2, h);
            s += `<path class="bar${dates[i] === today ? ' is-today' : ''}" data-i="${i}" d="M${x0},${y0}V${yt + r}Q${x0},${yt} ${x0 + r},${yt}H${x0 + bw - r}Q${x0 + bw},${yt} ${x0 + bw},${yt + r}V${y0}Z"/>`;
        });
        if (total > 0 && N >= 4 && dates[best] !== today) { const bx = xc(best), by = y(max); s += `<text class="best-t" x="${bx}" y="${by - 8}" text-anchor="${bx > W - 60 ? 'end' : bx < pad.l + 30 ? 'start' : 'middle'}">${metric === 'rev' ? short(max) : max}</text>`; }
        s += '</svg>';
        s += total === 0 ? `<div class="td-empty"><p>Belum ada ${metric === 'rev' ? 'pendapatan' : 'pesanan'} dalam ${N} hari terakhir</p></div>` : '<div class="td-tip" role="presentation"></div>';
        host.innerHTML = s; tip = host.querySelector('.td-tip');
        if (active !== null) show(active, false);
    }

    function show(i, announce) {
        if (!geo || !tip) return; active = i;
        const vals = series[metric], v = vals[i], x = geo.xc(i), yy = geo.y(v);
        host.querySelectorAll('.bar').forEach(b => b.classList.toggle('is-dim', Number(b.dataset.i) !== i));
        const isToday = dates[i] === today;
        let rel = isToday ? 'Hari ini (belum selesai)' : (v === 0 ? 'Tidak ada ' + (metric === 'rev' ? 'pendapatan' : 'pesanan') : '');
        if (!rel && metric === 'rev') { const done = vals.slice(0, -1), avg = done.reduce((a, b) => a + b, 0) / Math.max(1, done.length); if (avg > 0) { const d = Math.round((v - avg) / avg * 100); rel = (d >= 0 ? '+' : '−') + Math.abs(d) + '% dari rata-rata'; } }
        tip.innerHTML = `<b>${dLong.format(parse(dates[i]))}</b><strong>${fmt(v)}</strong>${rel ? `<span>${rel}</span>` : ''}`;
        tip.style.left = Math.min(Math.max(x, 96), geo.W - 96) + 'px'; tip.style.top = Math.max(yy, 56) + 'px'; tip.classList.add('is-on');
        if (announce && live) live.textContent = `${dLong.format(parse(dates[i]))}: ${fmt(v)}`;
    }
    function hide() { active = null; host.querySelectorAll('.bar').forEach(b => b.classList.remove('is-dim')); if (tip) tip.classList.remove('is-on'); }
    const idxAt = e => { const r = host.getBoundingClientRect(); return Math.max(0, Math.min(N - 1, Math.floor((e.clientX - r.left - geo.pad.l) / geo.slot))); };

    host.addEventListener('pointermove', e => { if (geo && Math.max(0, ...series[metric]) > 0) show(idxAt(e), false); });
    host.addEventListener('pointerleave', hide);
    host.addEventListener('focus', () => { if (Math.max(0, ...series[metric]) > 0) show(active ?? N - 1, true); });
    host.addEventListener('blur', hide);
    host.addEventListener('keydown', e => {
        if (Math.max(0, ...series[metric]) === 0) return; let i = active ?? N - 1;
        if (e.key === 'ArrowRight') i = Math.min(N - 1, i + 1); else if (e.key === 'ArrowLeft') i = Math.max(0, i - 1);
        else if (e.key === 'Home') i = 0; else if (e.key === 'End') i = N - 1; else if (e.key === 'Escape') { hide(); return; } else return;
        e.preventDefault(); show(i, true);
    });

    document.querySelectorAll('[data-metric]').forEach(b => b.addEventListener('click', () => {
        metric = b.dataset.metric; active = null;
        document.querySelectorAll('[data-metric]').forEach(x => x.setAttribute('aria-pressed', x === b ? 'true' : 'false'));
        stats(); build();
    }));

    stats(); build();
    let lastW = host.clientWidth, raf = 0;
    new ResizeObserver(() => { cancelAnimationFrame(raf); raf = requestAnimationFrame(() => { if (host.clientWidth !== lastW) { lastW = host.clientWidth; build(); } }); }).observe(host);
}

/** Dipanggil app.js sebelum Alpine.start(), supaya x-data="tdRange(...)" sudah terdaftar. */
export function registerDashboard(Alpine) {
    Alpine.data('tdRange', tdRange);
    initClickableRows();
    initSalesChart();
}
