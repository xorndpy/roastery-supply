@php
    $ga = $site['ga4_id'] ?? null;
    $px = $site['meta_pixel_id'] ?? null;
    // Validasi ulang saat render: ID dari database tidak pernah dicetak mentah ke dalam <script>.
    $ga = $ga && preg_match('/^G-[A-Z0-9]{6,12}$/', $ga) ? $ga : null;
    $px = $px && preg_match('/^[0-9]{8,20}$/', $px) ? $px : null;
    // Cloudflare Web Analytics: token dari .env (CF_BEACON_TOKEN), divalidasi sebelum dicetak ke <script>.
    // Beacon ini tanpa cookie, sehingga tidak memerlukan persetujuan.
    $cf = config('services.cloudflare.beacon_token');
    $cf = $cf && preg_match('/^[A-Za-z0-9]{16,64}$/', $cf) ? $cf : null;
@endphp
@if($cf)
    <script type="module" src="https://static.cloudflareinsights.com/beacon.min.js" data-cf-beacon='{"token": "{{ $cf }}"}'></script>
@endif
<script>
    // GA4 dan Meta Pixel memasang cookie pelacak, jadi baru dimuat setelah pengunjung menyetujui
    // lewat banner (komponen cookie-consent). Pilihan disimpan di localStorage ("granted" / "denied").
    (function () {
        var KEY = 'koff_consent_v1', GA = @js($ga), PX = @js($px);
        var consent = {
            get: function () { try { return localStorage.getItem(KEY); } catch (e) { return null; } },
            set: function (v) { try { localStorage.setItem(KEY, v); } catch (e) {} }
        };
        window.koffConsent = consent;
        window.koffHasTrackers = !!(GA || PX);

        window.koffLoadTrackers = function () {
            if (window.__koffTrackers) return;
            window.__koffTrackers = true;
            if (GA) {
                var s = document.createElement('script');
                s.async = true;
                s.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(GA);
                document.head.appendChild(s);
                window.dataLayer = window.dataLayer || [];
                window.gtag = function () { window.dataLayer.push(arguments); };
                window.gtag('js', new Date());
                window.gtag('config', GA);
            }
            if (PX) {
                (function (f, b, e, v, n, t, s) {
                    if (f.fbq) return;
                    n = f.fbq = function () { n.callMethod ? n.callMethod.apply(n, arguments) : n.queue.push(arguments); };
                    if (!f._fbq) f._fbq = n;
                    n.push = n; n.loaded = true; n.version = '2.0'; n.queue = [];
                    t = b.createElement(e); t.async = true; t.src = v;
                    s = b.getElementsByTagName(e)[0]; s.parentNode.insertBefore(t, s);
                })(window, document, 'script', 'https://connect.facebook.net/en_US/fbevents.js');
                window.fbq('init', PX);
                window.fbq('track', 'PageView');
            }
        };

        // Hapus cookie pelacak saat pengunjung menolak atau mencabut persetujuan.
        window.koffClearTrackers = function () {
            var parts = location.hostname.split('.'), domains = [location.hostname];
            for (var i = 1; i < parts.length - 1; i++) domains.push('.' + parts.slice(i).join('.'));
            document.cookie.split(';').forEach(function (c) {
                var name = c.split('=')[0].trim();
                if (!/^(_ga|_gid|_gat|_gcl|_fbp|_fbc)/.test(name)) return;
                var gone = name + '=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/';
                document.cookie = gone;
                domains.forEach(function (d) { document.cookie = gone + '; domain=' + d; });
            });
        };

        if (consent.get() === 'granted') window.koffLoadTrackers();
    })();

    // Helper event: window.track('view_item' | 'add_to_cart' | 'generate_lead', { name, value, id })
    // Aman dipanggil kapan saja: tidak melakukan apa pun bila pelacak belum dimuat.
    window.track = function (event, p) {
        p = p || {};
        try {
            if (typeof gtag === 'function') {
                gtag('event', event, { currency: 'IDR', value: p.value || 0, items: [{ item_id: p.id, item_name: p.name, price: p.value || 0, quantity: p.qty || 1 }] });
            }
            if (typeof fbq === 'function') {
                var map = { view_item: 'ViewContent', add_to_cart: 'AddToCart', generate_lead: 'Lead' };
                if (map[event]) fbq('track', map[event], { content_name: p.name, content_ids: [String(p.id || '')], content_type: 'product', value: p.value || 0, currency: 'IDR' });
            }
        } catch (e) {}
    };
</script>
