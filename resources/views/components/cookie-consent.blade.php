{{-- Banner persetujuan cookie. Hanya muncul bila GA4 atau Meta Pixel aktif (lihat komponen analytics). --}}
<style>
    #koff-cookie { position: fixed; z-index: 70; left: 16px; right: 16px; bottom: 16px; max-width: 460px; background: #fff; color: #44403c; border: 1px solid #e7e5e4; border-radius: 14px; box-shadow: 0 12px 40px rgba(28, 25, 23, .18); padding: 18px 18px 14px; font-size: .875rem; line-height: 1.55; }
    #koff-cookie[hidden] { display: none; }
    @media (min-width: 640px) { #koff-cookie { right: auto; left: 20px; bottom: 20px; } }
    #koff-cookie .koff-t { margin: 0 0 6px; font-size: 1rem; font-weight: 700; color: #1c1917; }
    #koff-cookie p { margin: 0 0 14px; }
    #koff-cookie .koff-a { display: flex; gap: 10px; }
    #koff-cookie button { flex: 1; min-height: 46px; padding: 0 16px; border-radius: 10px; border: 2px solid #4a2f21; font: inherit; font-weight: 600; cursor: pointer; }
    #koff-cookie button[data-koff="denied"] { background: #fff; color: #4a2f21; }
    #koff-cookie button[data-koff="granted"] { background: #4a2f21; color: #fff; }
    #koff-cookie button:focus-visible, #koff-cookie a:focus-visible { outline: 3px solid #a8643a; outline-offset: 2px; }
    #koff-cookie .koff-l { display: inline-block; margin-top: 10px; color: #8f5230; text-decoration: underline; text-underline-offset: 2px; }
</style>
<div id="koff-cookie" role="region" aria-labelledby="koff-cookie-title" hidden>
    <p id="koff-cookie-title" class="koff-t">Cookie dan privasi Anda</p>
    <p>Kami memakai cookie analitik dan iklan untuk memahami kunjungan dan mengukur iklan. Cookie yang penting untuk login dan keranjang selalu aktif. Pilihan ini bebas dan dapat Anda ubah kapan saja lewat &ldquo;Pengaturan cookie&rdquo; di bagian bawah halaman.</p>
    <div class="koff-a">
        <button type="button" data-koff="denied">Tolak</button>
        <button type="button" data-koff="granted">Terima</button>
    </div>
    <a class="koff-l" href="{{ route('legal.privacy') }}#cookie">Baca Kebijakan Privasi</a>
</div>
<script>
    (function () {
        var box = document.getElementById('koff-cookie');
        if (!box || !window.koffHasTrackers || !window.koffConsent) return;
        function show() { box.hidden = false; }
        function hide() { box.hidden = true; }

        if (window.koffConsent.get() === null) show();

        box.querySelectorAll('[data-koff]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var v = btn.getAttribute('data-koff');
                window.koffConsent.set(v);
                if (v === 'granted') {
                    window.koffLoadTrackers();
                } else {
                    window.koffClearTrackers();
                    if (window.__koffTrackers) { location.reload(); return; }
                }
                hide();
            });
        });

        window.koffOpenCookie = function () { show(); var b = box.querySelector('button'); if (b) b.focus(); };
        document.querySelectorAll('[data-koff-open]').forEach(function (el) {
            el.hidden = false;
            el.addEventListener('click', window.koffOpenCookie);
        });
    })();
</script>
