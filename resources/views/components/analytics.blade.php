@php
    $ga = $site['ga4_id'] ?? null;
    $px = $site['meta_pixel_id'] ?? null;
    // Validasi ulang saat render: ID dari database tidak pernah dicetak mentah ke dalam <script>.
    $ga = $ga && preg_match('/^G-[A-Z0-9]{6,12}$/', $ga) ? $ga : null;
    $px = $px && preg_match('/^[0-9]{8,20}$/', $px) ? $px : null;
@endphp
@if($ga)
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ $ga }}"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){ dataLayer.push(arguments); }
        gtag('js', new Date());
        gtag('config', @js($ga));
    </script>
@endif
@if($px)
    <script>
        !function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');
        fbq('init', @js($px));
        fbq('track', 'PageView');
    </script>
@endif
<script>
    // Helper event: window.track('view_item' | 'add_to_cart' | 'generate_lead', { name, value, id })
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
