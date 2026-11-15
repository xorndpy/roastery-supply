@php
    // Etalase flash sale: kampanye berjalan yang paling cepat berakhir. Hilang sendiri bila tidak ada promo aktif.
    $svc = app(\App\Services\FlashSaleService::class);
    $campaign = $svc->activeCampaign();
    $items = collect();
    $sold = [];
    if ($campaign) {
        $items = $campaign->items()->with(['product.images', 'product.brand'])->get()->filter(fn ($i) => $i->product && $i->product->is_active);
        $sold = $svc->soldFor($items->pluck('id')->all());
        $items = $items->take(8);
    }
@endphp

@if($campaign && $items->isNotEmpty())
<section class="container-x pb-4 pt-10" aria-labelledby="flash-title" x-data="flashTimer({{ $campaign->ends_at->timestamp * 1000 }}, {{ now()->timestamp * 1000 }})" x-init="start()">
    <div class="rounded-3xl bg-[#2b1c14] p-5 text-white sm:p-7">
        <div class="mb-5 flex flex-wrap items-center justify-between gap-x-6 gap-y-3">
            <div class="flex flex-wrap items-center gap-x-5 gap-y-2">
                <h2 id="flash-title" class="flex items-center gap-2 text-xl font-bold sm:text-2xl">
                    <svg class="h-6 w-6 text-orange-400" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M13 2 4 14h6l-1 8 9-12h-6l1-8z"/></svg>
                    Flash Sale
                </h2>
                <div class="flex items-center gap-2" role="timer" aria-label="Sisa waktu promo">
                    <span class="text-xs text-orange-200/80">Berakhir dalam</span>
                    <span x-show="d > 0" x-cloak class="text-sm font-semibold text-orange-200" x-text="d + ' hari'"></span>
                    <span class="flex items-center gap-1 font-bold tabular-nums text-[#2b1c14]">
                        <span class="min-w-8 rounded-lg bg-[#f3e3cf] px-1.5 py-1 text-center text-sm" x-text="h">--</span><span class="text-orange-200">:</span>
                        <span class="min-w-8 rounded-lg bg-[#f3e3cf] px-1.5 py-1 text-center text-sm" x-text="m">--</span><span class="text-orange-200">:</span>
                        <span class="min-w-8 rounded-lg bg-[#f3e3cf] px-1.5 py-1 text-center text-sm" x-text="s">--</span>
                    </span>
                </div>
            </div>
            <a href="{{ route('products.index', ['flash' => 1]) }}" class="rounded-full border border-orange-200/40 px-4 py-1.5 text-sm text-orange-100 transition hover:bg-white/10">Lihat semua →</a>
        </div>

        <p class="mb-4 text-sm text-orange-100/80">{{ $campaign->name }}</p>

        <div class="-mx-1 flex snap-x gap-4 overflow-x-auto px-1 pb-2">
            @foreach($items as $item)
                @php
                    $p = $item->product;
                    $img = $p->images->first();
                    $soldN = $sold[$item->id] ?? 0;
                    $left = max(0, $item->quota - $soldN);
                    $bar = $item->quota > 0 ? min(100, (int) round($soldN / $item->quota * 100)) : 0;
                    $out = $left <= 0 || $p->stock <= 0;
                @endphp
                <a href="{{ route('products.show', $p->slug) }}" class="group relative flex w-[200px] shrink-0 snap-start flex-col overflow-hidden rounded-2xl bg-white text-stone-900 sm:w-[calc(25%-12px)] {{ $out ? 'opacity-60' : '' }}">
                    <div class="relative aspect-square bg-stone-50">
                        @if($img)
                            <img src="{{ thumb_url($img->image) }}" alt="{{ $p->name }}" loading="lazy" decoding="async" onerror="this.onerror=null;this.src='{{ media_url($img->image) }}'" class="h-full w-full object-contain p-3 transition group-hover:scale-[1.03]">
                        @endif
                        <span class="absolute left-3 top-3 rounded-full bg-orange-500 px-2.5 py-1 text-xs font-bold text-white">-{{ $item->discountPercent() }}%</span>
                        @if($out)
                            <span class="absolute inset-0 grid place-items-center bg-white/70 text-sm font-semibold text-stone-700">Habis</span>
                        @elseif($left <= 5)
                            <span class="absolute bottom-3 left-3 rounded-full bg-[#2b1c14] px-2 py-0.5 text-[11px] text-orange-200">Tinggal {{ $left }}</span>
                        @endif
                    </div>
                    <div class="flex flex-1 flex-col p-4">
                        @if($p->brand)<span class="text-[11px] uppercase tracking-wide text-stone-500">{{ $p->brand->name }}</span>@endif
                        <span class="mt-0.5 line-clamp-2 text-sm font-medium leading-snug">{{ $p->name }}</span>
                        <div class="mt-2 flex flex-wrap items-baseline gap-x-2">
                            <span class="font-bold text-orange-700">Rp {{ number_format($item->sale_price, 0, ',', '.') }}</span>
                            <span class="text-xs text-stone-400 line-through">Rp {{ number_format($p->price, 0, ',', '.') }}</span>
                        </div>
                        <div class="mt-3" role="progressbar" aria-valuemin="0" aria-valuemax="{{ $item->quota }}" aria-valuenow="{{ $soldN }}" aria-label="Terjual {{ $soldN }} dari {{ $item->quota }}">
                            <div class="h-1.5 overflow-hidden rounded-full bg-orange-100"><div class="h-full rounded-full bg-orange-500" style="width: {{ $bar }}%"></div></div>
                            <p class="mt-1 text-[11px] text-stone-500">Terjual {{ $soldN }} dari {{ $item->quota }}</p>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
    </div>
</section>

@once
<script>
    // Hitung mundur memakai jam server (selisih dihitung sekali) supaya jam perangkat yang salah tidak memengaruhi.
    window.flashTimer = function (end, serverNow) {
        const offset = serverNow - Date.now();
        const pad = n => String(n).padStart(2, '0');
        return {
            d: 0, h: '--', m: '--', s: '--', timer: null,
            start() {
                const tick = () => {
                    const left = Math.max(0, end - (Date.now() + offset));
                    const total = Math.floor(left / 1000);
                    this.d = Math.floor(total / 86400);
                    this.h = pad(Math.floor((total % 86400) / 3600));
                    this.m = pad(Math.floor((total % 3600) / 60));
                    this.s = pad(total % 60);
                    if (left === 0) { clearInterval(this.timer); setTimeout(() => location.reload(), 1500); }
                };
                tick();
                this.timer = setInterval(tick, 1000);
            },
        };
    };
</script>
@endonce
@endif
