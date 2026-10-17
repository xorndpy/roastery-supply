@extends('layouts.app')

@section('title', ($site['meta_title'] ?? null) ?: $site['site_name'] . ' — ' . $site['tagline'])

@section('content')

{{-- ============ HERO ============ --}}
@php
    // Produk unggulan pertama yang punya model 3D (.glb) tampil di hero; bila tidak ada, pakai mesin ilustrasi.
    $modelProduct = $featuredProducts->first(fn ($p) => !empty($p->model_3d));
    $spot = $modelProduct ?? $featuredProducts->first();
@endphp
<section class="relative overflow-hidden bg-gradient-to-br from-[#24150e] to-brand text-white">
    @if(!empty($site['hero_bg']))
        {{-- Background hero (Admin > Pengaturan > Tampilan > Background Beranda). --}}
        {{-- Foto dibiarkan jelas (tanpa blur). Gelap hanya di belakang teks, lalu memudar ke kanan agar foto tetap terlihat. --}}
        <img src="{{ asset('storage/' . $site['hero_bg']) }}" alt="" class="absolute inset-0 h-full w-full object-cover" fetchpriority="high">
        {{-- Mobile: tata letak bertumpuk, jadi overlay merata. Desktop: gradasi horizontal. --}}
        <div class="absolute inset-0 bg-gradient-to-b from-[#1a0f0a]/90 via-[#1a0f0a]/80 via-[52%] to-[#1a0f0a]/25 lg:hidden"></div>
        <div class="absolute inset-0 hidden bg-gradient-to-r from-[#1a0f0a]/90 via-[#1a0f0a]/75 via-[44%] to-[#1a0f0a]/0 lg:block"></div>
        <div class="absolute inset-x-0 bottom-0 h-1/3 bg-gradient-to-t from-[#1a0f0a]/55 to-transparent"></div>
    @endif
    <div class="container-x relative grid items-center gap-12 py-14 lg:grid-cols-[1.1fr_1fr] lg:py-20">
        <div>
            <div class="text-xs font-semibold uppercase tracking-[0.14em] text-orange-300">{{ $site['site_name'] }}</div>
            <h1 class="heading mt-4 text-4xl leading-[1.12] sm:text-5xl">{{ $site['tagline'] }}</h1>
            <p class="mt-5 max-w-md text-base leading-relaxed text-stone-100">
                Espresso machine, grinder, dan perlengkapan barista dengan garansi resmi serta layanan sparepart.
            </p>
            <div class="mt-8 flex flex-wrap gap-3">
                <a href="{{ route('products.index') }}" class="btn btn-light">Lihat Katalog</a>
                <a href="{{ route('contact.index') }}" class="btn btn-ghost-light">Konsultasi Gratis</a>
            </div>
        </div>

        {{-- Model 3D (three.js). Ganti dengan <model-viewer> + file .glb produk bila sudah ada. --}}
        <div id="hero3d" @if($modelProduct) data-model="1" @endif class="relative aspect-[5/4]">
            {{-- Latar transparan: model melayang di atas background hero. Cahaya hangat tipis memberi kedalaman. --}}
            {{-- Dengan foto: vignette gelap tipis agar model tetap terpisah dari latar. Tanpa foto: cahaya hangat. --}}
            <div class="pointer-events-none absolute inset-0 {{ !empty($site['hero_bg']) ? 'bg-[radial-gradient(ellipse_at_50%_55%,rgba(26,15,10,0.55),transparent_68%)]' : 'bg-[radial-gradient(ellipse_at_50%_55%,rgba(168,100,58,0.28),transparent_62%)]' }}"></div>
            <span class="pointer-events-none absolute left-4 top-3.5 z-10 text-[11px] uppercase tracking-[0.1em] text-white/80 [text-shadow:0_1px_8px_rgba(0,0,0,.5)]">Geser untuk memutar</span>
            @if($modelProduct)
                <model-viewer src="{{ asset('storage/' . $modelProduct->model_3d) }}" alt="{{ $modelProduct->name }}"
                              camera-controls auto-rotate rotation-per-second="18deg" interaction-prompt="none"
                              shadow-intensity="1.2" shadow-softness="1" exposure="1.1" environment-image="neutral"
                              class="absolute inset-0 h-full w-full" style="--poster-color: transparent; background: transparent;"></model-viewer>
            @endif
            @if($spot)
                <a href="{{ route('products.show', $spot->slug) }}"
                   class="absolute inset-x-4 bottom-4 z-10 flex items-center justify-between rounded-lg border border-white/15 bg-[#1a0f0a]/55 px-4 py-3 backdrop-blur-md transition hover:bg-[#1a0f0a]/70">
                    <div>
                        <div class="text-[11px] uppercase tracking-[0.08em] text-stone-200">{{ $spot->brand->name ?? 'Unggulan' }}</div>
                        <div class="text-sm">{{ $spot->name }}</div>
                    </div>
                    <div class="text-sm font-semibold text-orange-200">Rp {{ number_format($spot->price, 0, ',', '.') }}</div>
                </a>
            @endif
        </div>
    </div>
</section>

{{-- ============ BANNER PROMO ============ --}}
@if($banners->isNotEmpty())
    <section class="container-x pt-10">
        <div class="flex snap-x snap-mandatory gap-4 overflow-x-auto pb-2">
            @foreach($banners as $b)
                @php $tag = $b->link ? 'a' : 'div'; @endphp
                <{{ $tag }} @if($b->link) href="{{ $b->link }}" @endif
                    class="block shrink-0 basis-full snap-start overflow-hidden rounded-xl border border-stone-200 sm:basis-[calc(50%-8px)] lg:basis-[calc(33.333%-11px)]">
                    <img src="{{ asset('storage/' . $b->image) }}" alt="{{ $b->title }}" class="aspect-[16/7] w-full object-cover transition duration-300 hover:scale-[1.02]">
                </{{ $tag }}>
            @endforeach
        </div>
    </section>
@endif

{{-- ============ LAYANAN ============ --}}
{{-- Isi daftar ini boleh diubah langsung. Hanya tuliskan hal yang memang berlaku untuk usaha Anda. --}}
@php
    $services = [
        ['Konsultasi kebutuhan', 'Rekomendasi mesin dan peralatan disesuaikan dengan kapasitas, konsep usaha, dan anggaran Anda.'],
        ['Produk resmi bergaransi', 'Setiap unit dipasok dari distributor resmi dan dilengkapi garansi resmi.'],
        ['Pengiriman terasuransi', 'Unit dikemas rapi dan diasuransikan selama proses pengiriman hingga lokasi Anda.'],
        ['Instalasi, servis, dan suku cadang', 'Pemasangan di lokasi, pelatihan dasar bagi barista, serta teknisi dan suku cadang untuk perawatan jangka panjang.'],
    ];
@endphp
<section class="container-x pt-20" aria-labelledby="layanan-title">
    <div class="grid gap-10 lg:grid-cols-[5fr_7fr] lg:gap-20">
        <div class="lg:sticky lg:top-28 lg:self-start">
            <p class="eyebrow">Layanan kami</p>
            <h2 id="layanan-title" class="heading mt-3 text-3xl leading-[1.15] sm:text-[2.25rem] [text-wrap:balance]">Peralatan yang tepat, didampingi hingga purna jual.</h2>
            <p class="mt-4 max-w-md text-[15px] leading-relaxed text-stone-600">Kami mendampingi pemilik kedai kopi dan roastery sejak tahap perencanaan hingga peralatan beroperasi dengan baik.</p>
            <a href="{{ route('about') }}" class="mt-6 inline-flex items-center gap-1.5 text-sm font-medium text-orange-700 transition hover:gap-2.5 hover:underline">Kenali kami lebih jauh <span aria-hidden="true">→</span></a>
        </div>

        <ol class="svc-list">
            @foreach($services as $i => [$title, $desc])
                <li class="svc-row" data-reveal style="--d: {{ $i * 90 }}ms">
                    <span class="svc-no" aria-hidden="true">0{{ $i + 1 }}</span>
                    <div>
                        <h3 class="svc-title">{{ $title }}</h3>
                        <p class="svc-desc">{{ $desc }}</p>
                    </div>
                </li>
            @endforeach
        </ol>
    </div>
</section>

{{-- ============ KATEGORI (bento) ============ --}}
@if($categories->isNotEmpty())
    <section class="container-x pt-16">
        <div class="mb-6 flex items-end justify-between">
            <h2 class="heading text-3xl">Belanja per Kategori</h2>
            <a href="{{ route('products.index') }}" class="text-sm text-orange-600 hover:underline">Semua produk →</a>
        </div>
        <div class="grid grid-cols-2 gap-3.5 md:auto-rows-[168px] md:grid-cols-4">
            @foreach($categories->take(5) as $i => $c)
                <a href="{{ route('products.index', ['category' => $c->slug]) }}"
                   class="group flex flex-col justify-between rounded-xl p-5 transition
                          {{ $i === 0 ? 'col-span-2 min-h-[200px] bg-brand p-7 text-white hover:bg-brand-dark md:row-span-2' : 'min-h-[120px] bg-stone-100 hover:bg-stone-200' }}">
                    <div class="flex justify-between text-xs {{ $i === 0 ? 'text-white/70' : 'text-stone-500' }}">
                        <span>{{ isset($c->products_count) ? $c->products_count . ' produk' : 'Kategori' }}</span>
                        <span class="transition group-hover:translate-x-1">→</span>
                    </div>
                    <div>
                        <div class="heading {{ $i === 0 ? 'text-3xl leading-tight' : 'text-lg' }}">{{ $c->name }}</div>
                        @if($i === 0 && !empty($c->description))
                            <p class="mt-2 max-w-xs text-sm text-white/70">{{ \Illuminate\Support\Str::limit($c->description, 90) }}</p>
                        @endif
                    </div>
                </a>
            @endforeach
        </div>
    </section>
@endif

{{-- ============ PRODUK UNGGULAN ============ --}}
@if($featuredProducts->isNotEmpty())
    <section class="container-x pt-16">
        <div class="mb-6 flex items-end justify-between">
            <h2 class="heading text-3xl">Produk Unggulan</h2>
            <a href="{{ route('products.index') }}" class="text-sm text-orange-600 hover:underline">Lihat semua →</a>
        </div>
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4 lg:gap-5">
            @foreach($featuredProducts as $product)
                <x-product-card :product="$product" />
            @endforeach
        </div>
    </section>
@endif

{{-- ============ MEREK & REKANAN ============ --}}
@if($partners->isNotEmpty())
    <section class="mt-16 border-y border-stone-200 bg-white">
        <div class="container-x py-12">
            <h2 class="mb-8 text-center text-xs font-semibold uppercase tracking-[0.14em] text-stone-500">Merek &amp; Rekanan</h2>
            <div class="grid grid-cols-2 gap-3.5 sm:grid-cols-3 lg:grid-cols-6">
                @foreach($partners as $p)
                    @php $logo = '<img src="' . e(asset('storage/' . $p->logo)) . '" alt="' . e($p->name) . '" loading="lazy" class="max-h-12 max-w-[130px] object-contain opacity-60 grayscale transition group-hover:opacity-100 group-hover:grayscale-0">'; @endphp
                    @if($p->link)
                        <a href="{{ $p->link }}" target="_blank" rel="noopener noreferrer" class="group flex h-28 items-center justify-center rounded-xl border border-stone-200 bg-white p-3 transition hover:border-orange-500">{!! $logo !!}</a>
                    @else
                        <div class="group flex h-28 items-center justify-center rounded-xl border border-stone-200 bg-white p-3">{!! $logo !!}</div>
                    @endif
                @endforeach
            </div>
        </div>
    </section>
@endif

{{-- ============ TESTIMONI ============ --}}
@if($testimonials->isNotEmpty())
    <section class="container-x pt-16">
        <h2 class="heading mb-6 text-3xl">Kata Pelanggan</h2>
        <div class="grid gap-4 md:grid-cols-3">
            @foreach($testimonials as $t)
                <figure class="flex flex-col rounded-xl border border-stone-200 bg-white p-6">
                    <div class="flex gap-0.5" role="img" aria-label="{{ $t->rating }} dari 5">
                        @for($s = 1; $s <= 5; $s++)
                            <svg class="h-4 w-4 {{ $s <= $t->rating ? 'text-amber-500' : 'text-stone-200' }}" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 1.8l2.6 5.4 5.9.8-4.3 4.1 1 5.9L10 15.2l-5.2 2.8 1-5.9L1.5 8l5.9-.8L10 1.8z"/></svg>
                        @endfor
                    </div>
                    <blockquote class="mt-3 flex-1 whitespace-pre-line text-sm leading-relaxed text-stone-600">{{ $t->content }}</blockquote>
                    <figcaption class="mt-4 text-sm font-medium">{{ $t->customer_name }}</figcaption>
                </figure>
            @endforeach
        </div>
    </section>
@endif

{{-- ============ CTA ============ --}}
<section class="container-x pt-16">
    <div class="flex flex-col items-start justify-between gap-6 rounded-2xl bg-brand p-8 text-white sm:flex-row sm:items-center sm:p-10">
        <div>
            <h2 class="heading text-2xl sm:text-3xl">Butuh rekomendasi mesin?</h2>
            <p class="mt-1 text-sm text-white/75">Ceritakan kebutuhan kedai Anda, kami bantu pilihkan.</p>
        </div>
        <a href="{{ route('contact.index') }}" class="btn bg-white text-brand hover:bg-orange-100">Hubungi Kami</a>
    </div>
</section>

@endsection

@push('scripts')
@if($modelProduct)
<script type="module" src="https://ajax.googleapis.com/ajax/libs/model-viewer/3.5.0/model-viewer.min.js"></script>
@endif
<script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
<script>
(function () {
    const el = document.getElementById('hero3d');
    if (!el || el.dataset.model || !window.THREE) return;
    try {
        const T = THREE, PI = Math.PI;
        const r = new T.WebGLRenderer({ antialias: true, alpha: true });
        r.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
        const c = r.domElement;
        c.style.cssText = 'position:absolute;inset:0;width:100%;height:100%;cursor:grab;touch-action:pan-y';
        el.prepend(c);

        const s = new T.Scene();
        const cam = new T.PerspectiveCamera(36, 1, .1, 50);
        cam.position.set(0, 1, 7.4); cam.lookAt(0, -.1, 0);
        s.add(new T.HemisphereLight(0xfff1e0, 0x3a2418, .95));
        const d = new T.DirectionalLight(0xffe2c4, 1.1); d.position.set(4, 5, 5); s.add(d);
        const pl = new T.PointLight(0xc68b59, 1, 20); pl.position.set(-4, 1, 3); s.add(pl);
        const rim = new T.DirectionalLight(0xffffff, .55); rim.position.set(-3, 2, -4); s.add(rim);

        const M = (col, m, ro) => new T.MeshStandardMaterial({ color: col, metalness: m, roughness: ro });
        const steel = M(0xb9b4ae, .6, .3), dark = M(0x2c2622, .4, .5), cop = M(0xb87333, .7, .3),
              cream = M(0xefe6dc, .1, .6), blk = M(0x151210, .3, .6), white = M(0xffffff, .1, .4);
        const g = new T.Group(); s.add(g);
        const B = (w, h, e) => new T.BoxGeometry(w, h, e), C = (a, b, h) => new T.CylinderGeometry(a, b, h, 32);
        const add = (geo, mat, x, y, z, rx, rz) => { const m = new T.Mesh(geo, mat); m.position.set(x, y, z); m.rotation.x = rx || 0; m.rotation.z = rz || 0; g.add(m); return m; };

        add(B(3.3, .16, 2), steel, 0, -1, .1);
        add(B(3.1, .04, 1.8), blk, 0, -.9, .1);
        add(B(3, 1.9, 1.6), dark, 0, .05, 0);
        add(B(3.04, .2, 1.64), cop, 0, .55, 0);
        add(B(3.1, .12, 1.7), steel, 0, 1.06, 0);
        [-1, -.35, .35, 1].forEach(x => add(C(.17, .13, .2), cream, x, 1.22, -.2));
        add(new T.TorusGeometry(.28, .04, 12, 40), cop, 0, .15, .84);
        add(C(.27, .27, .08), cream, 0, .15, .83, PI / 2);
        add(B(.03, .2, .02), cop, 0, .2, .88, 0, -.6);
        [-1.3, -1.05, 1.05, 1.3].forEach(x => add(C(.07, .07, .08), steel, x, .15, .82, PI / 2));
        [-.7, .7].forEach(x => {
            add(C(.3, .3, .3), steel, x, -.3, .82);
            add(C(.25, .2, .16), steel, x, -.5, .82);
            add(C(.06, .06, 1), blk, x, -.5, 1.4, PI / 2);
            add(C(.22, .17, .3), white, x, -.75, .82);
        });
        add(C(.045, .045, 1.1), steel, 1.7, -.15, .3);
        add(new T.SphereGeometry(.1, 16, 16), blk, 1.7, .45, .3);

        const cv = document.createElement('canvas'); cv.width = cv.height = 128;
        const x2 = cv.getContext('2d'), gr = x2.createRadialGradient(64, 64, 0, 64, 64, 64);
        gr.addColorStop(0, 'rgba(0,0,0,.55)'); gr.addColorStop(1, 'rgba(0,0,0,0)'); x2.fillStyle = gr; x2.fillRect(0, 0, 128, 128);
        const sh = new T.Mesh(new T.PlaneGeometry(6.4, 3.4), new T.MeshBasicMaterial({ map: new T.CanvasTexture(cv), transparent: true, depthWrite: false }));
        sh.rotation.x = -PI / 2; sh.position.set(0, -1.12, .1); s.add(sh);

        const puffs = [];
        for (let i = 0; i < 8; i++) {
            const m = new T.Mesh(new T.SphereGeometry(.08, 10, 10), new T.MeshBasicMaterial({ color: 0xffffff, transparent: true, opacity: 0, depthWrite: false }));
            g.add(m); puffs.push(m);
        }

        const rs = () => { const w = el.clientWidth, h = el.clientHeight; if (!w || !h) return; r.setSize(w, h, false); cam.aspect = w / h; cam.updateProjectionMatrix(); };
        new ResizeObserver(rs).observe(el); rs();

        let ux = 0, uy = 0, dn = false, lx = 0, ly = 0, vis = true;
        c.addEventListener('pointerdown', e => { dn = true; lx = e.clientX; ly = e.clientY; c.setPointerCapture(e.pointerId); c.style.cursor = 'grabbing'; });
        c.addEventListener('pointermove', e => { if (!dn) return; ux += (e.clientX - lx) * .01; uy = Math.max(-.4, Math.min(.4, uy + (e.clientY - ly) * .005)); lx = e.clientX; ly = e.clientY; });
        ['pointerup', 'pointercancel'].forEach(n => c.addEventListener(n, () => { dn = false; c.style.cursor = 'grab'; }));
        new IntersectionObserver(e => { vis = e[0].isIntersecting; }).observe(el);

        const rm = window.matchMedia('(prefers-reduced-motion: reduce)').matches, clock = new T.Clock();
        (function tick() {
            requestAnimationFrame(tick); if (!vis) return;
            const t = clock.getElapsedTime();
            if (!dn) { ux *= .97; uy *= .97; }
            g.rotation.y = -.55 + (rm ? 0 : Math.sin(t * .5) * .45) + ux;
            g.rotation.x = uy * .5;
            g.position.y = rm ? 0 : Math.sin(t * .9) * .04;
            puffs.forEach((m, i) => {
                const k = rm ? 0 : (t * .22 + i / 8) % 1;
                m.position.set(1.7 + Math.sin(k * 7 + i) * .09, -.7 + k * 1.5, .3);
                m.scale.setScalar(1 + k * 2.6);
                m.material.opacity = .32 * (1 - k) * Math.min(k * 8, 1);
            });
            r.render(s, cam);
        })();
    } catch (e) { /* WebGL tidak tersedia: panel tetap tampil sebagai latar */ }
})();
</script>
@endpush
