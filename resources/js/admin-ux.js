// UX admin: (1) kunci tombol saat form dikirim agar tidak dobel klik,
// (2) aksi dengan data-undo ditunda 5 detik dengan toast "Urungkan".
if (document.body.classList.contains('admin-ui')) {
    const UNDO_MS = 5000;
    let pending = null;

    // ---------- Urungkan ----------
    const clearToast = () => { pending?.toast.remove(); };
    const commit = () => {
        if (!pending) return;
        const { form, submitter, timer } = pending;
        clearTimeout(timer); clearToast(); submitter?.classList.remove('is-pending'); pending = null;
        form.dataset.undoGo = '1';
        submitter ? form.requestSubmit(submitter) : form.requestSubmit();
    };
    const cancel = () => {
        if (!pending) return;
        clearTimeout(pending.timer); clearToast();
        pending.submitter?.classList.remove('is-pending'); pending.submitter?.removeAttribute('aria-busy');
        pending = null;
    };
    const startUndo = (form, submitter) => {
        if (pending) return;
        const toast = document.createElement('div');
        toast.className = 'undo-toast'; toast.setAttribute('role', 'status');
        const text = document.createElement('span'); text.textContent = form.dataset.undo;
        const btn = document.createElement('button'); btn.type = 'button'; btn.textContent = 'Urungkan';
        const bar = document.createElement('i'); bar.style.animationDuration = UNDO_MS + 'ms';
        toast.append(text, btn, bar); document.body.appendChild(toast);
        submitter?.classList.add('is-pending'); submitter?.setAttribute('aria-busy', 'true');
        btn.addEventListener('click', cancel); btn.focus({ preventScroll: true });
        pending = { form, submitter, toast, timer: setTimeout(commit, UNDO_MS) };
    };
    document.addEventListener('submit', (e) => {
        const f = e.target;
        if (!(f instanceof HTMLFormElement) || !f.dataset.undo) return;
        if (f.dataset.undoGo === '1') { delete f.dataset.undoGo; return; }
        e.preventDefault(); e.stopImmediatePropagation();
        startUndo(f, e.submitter || null);
    }, true);
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') cancel(); });
    // Pindah halaman saat menunggu: jalankan aksinya sekarang, jangan hilang diam-diam.
    window.addEventListener('pagehide', () => {
        if (!pending) return;
        const { form, submitter } = pending;
        try { fetch(form.action, { method: 'POST', body: new FormData(form, submitter), keepalive: true, credentials: 'same-origin' }); } catch (e) {}
        pending = null;
    });

    // ---------- Kunci tombol submit ----------
    const locked = [];
    const unlock = () => { while (locked.length) { const { el, label } = locked.pop(); el.disabled = false; el.classList.remove('is-busy'); if (label !== null) el.textContent = label; } };
    window.addEventListener('pageshow', unlock);
    document.addEventListener('submit', (e) => {
        const f = e.target;
        if (e.defaultPrevented || !(f instanceof HTMLFormElement)) return;
        if ((f.method || 'get').toLowerCase() === 'get' || 'noLock' in f.dataset) return;
        if (f.target === '_blank' || e.submitter?.formTarget === '_blank') return;
        // Ditunda satu tick: tombol yang di-disable lebih awal tidak ikut terkirim nilainya.
        setTimeout(() => {
            f.querySelectorAll('button:not([type="button"]), input[type="submit"]').forEach((el) => {
                if (el.disabled) return;
                const isSubmitter = el === e.submitter && el.tagName === 'BUTTON' && !el.children.length;
                locked.push({ el, label: isSubmitter ? el.textContent : null });
                el.disabled = true; el.classList.add('is-busy');
                if (isSubmitter) el.textContent = 'Memproses…';
            });
            setTimeout(unlock, 20000); // jaga-jaga bila respons berupa unduhan
        }, 0);
    });
}
