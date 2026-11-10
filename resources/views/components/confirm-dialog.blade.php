{{--
    Dialog konfirmasi global. Dipasang di semua layout lewat <x-confirm-dialog />.

    Cara pakai: tambahkan atribut ke <form> (atau tombol submit di dalam form):
      data-confirm-title   judul (wajib, ini yang mengaktifkan dialog)
      data-confirm-text    keterangan
      data-confirm-ok      teks tombol setuju      (default: "Ya, lanjutkan")
      data-confirm-cancel  teks tombol batal       (default: "Batal")
      data-confirm-tone    "danger" -> tombol setuju merah
--}}
<style>
    #cfm-overlay{position:fixed;inset:0;z-index:9999;display:none;align-items:center;justify-content:center;padding:1rem;background:rgba(28,25,23,.55)}
    #cfm-overlay.is-open{display:flex}
    #cfm-box{width:100%;max-width:24rem;background:#fff;border-radius:.75rem;padding:1.25rem;box-shadow:0 20px 50px rgba(0,0,0,.25);font-family:inherit}
    #cfm-title{margin:0;font-size:1rem;font-weight:600;color:#1c1917}
    #cfm-text{margin:.5rem 0 0;font-size:.875rem;line-height:1.45;color:#57534e}
    #cfm-actions{display:flex;justify-content:flex-end;gap:.5rem;margin-top:1.25rem}
    #cfm-actions button{border-radius:.5rem;padding:.5rem .9rem;font-size:.875rem;font-weight:500;cursor:pointer;border:1px solid transparent}
    #cfm-cancel{background:#fff;color:#44403c;border-color:#d6d3d1!important}
    #cfm-cancel:hover{background:#f5f5f4}
    #cfm-ok{background:#c2410c;color:#fff}
    #cfm-ok:hover{filter:brightness(.92)}
    #cfm-ok.is-danger{background:#b91c1c}
    #cfm-actions button:focus-visible{outline:2px solid #f97316;outline-offset:2px}
</style>

<div id="cfm-overlay" role="presentation" aria-hidden="true">
    <div id="cfm-box" role="alertdialog" aria-modal="true" aria-labelledby="cfm-title" aria-describedby="cfm-text">
        <h2 id="cfm-title"></h2>
        <p id="cfm-text"></p>
        <div id="cfm-actions">
            <button type="button" id="cfm-cancel"></button>
            <button type="button" id="cfm-ok"></button>
        </div>
    </div>
</div>

<script>
(function () {
    if (window.__cfmReady) return;
    window.__cfmReady = true;

    var overlay = document.getElementById('cfm-overlay');
    var elTitle = document.getElementById('cfm-title');
    var elText = document.getElementById('cfm-text');
    var btnOk = document.getElementById('cfm-ok');
    var btnCancel = document.getElementById('cfm-cancel');
    var onOk = null, lastFocus = null;

    function close() {
        overlay.classList.remove('is-open');
        overlay.setAttribute('aria-hidden', 'true');
        onOk = null;
        if (lastFocus && lastFocus.focus) lastFocus.focus();
    }

    function open(source, proceed) {
        var d = source.dataset;
        lastFocus = document.activeElement;
        elTitle.textContent = d.confirmTitle || 'Yakin?';
        elText.textContent = d.confirmText || '';
        elText.style.display = d.confirmText ? '' : 'none';
        btnOk.textContent = d.confirmOk || 'Ya, lanjutkan';
        btnCancel.textContent = d.confirmCancel || 'Batal';
        btnOk.classList.toggle('is-danger', d.confirmTone === 'danger');
        onOk = proceed;
        overlay.classList.add('is-open');
        overlay.setAttribute('aria-hidden', 'false');
        (d.confirmTone === 'danger' ? btnCancel : btnOk).focus();
    }

    btnOk.addEventListener('click', function () { var fn = onOk; close(); if (fn) fn(); });
    btnCancel.addEventListener('click', close);
    overlay.addEventListener('click', function (e) { if (e.target === overlay) close(); });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && overlay.classList.contains('is-open')) close();
    });

    function submitConfirmed(form, submitter, skipFormGuard) {
        if (!skipFormGuard) form.dataset.confirmed = '1';
        if (form.requestSubmit) { submitter ? form.requestSubmit(submitter) : form.requestSubmit(); }
        else { form.submit(); }
    }

    // 1) Atribut dipasang di <form>
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form.matches || !form.matches('form[data-confirm-title]')) return;
        if (form.dataset.confirmed === '1') { delete form.dataset.confirmed; return; }
        e.preventDefault();
        var submitter = e.submitter || null;
        open(form, function () { submitConfirmed(form, submitter); });
    }, true);

    // 2) Atribut dipasang di tombol submit
    document.addEventListener('click', function (e) {
        var btn = e.target.closest && e.target.closest('button[data-confirm-title], input[type=submit][data-confirm-title]');
        if (!btn || !btn.form) return;
        e.preventDefault();
        // requestSubmit(btn) tidak memicu click lagi, jadi tidak perlu flag di tombol
        open(btn, function () { submitConfirmed(btn.form, btn, true); });
    }, true);
})();
</script>