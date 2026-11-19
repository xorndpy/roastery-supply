@php
    // Etalase flash sale: semua promo yang sedang berjalan (dari semua kampanye). Hilang sendiri bila tidak ada.
    $svc = app(\App\Services\FlashSaleService::class);
    $show = $svc->showcase(12);
    $items = $show['items'] ?? collect();
@endphp

@if($show)
<section class="container-x pb-4 pt-6 sm:pt-10" aria-labelledby="flash-title" x-data="flashTimer({{ $show['ends_at']->timestamp * 1000 }}, {{ now()->timestamp * 1000 }})" x-init="start()">
    <div class="rounded-2xl bg-[#2b1c14] p-4 text-white sm:rounded-3xl sm:p-7">
        {{-- HP: [Flash Sale ........ Lihat semua] lalu timer di baris kedua. Desktop: satu baris (judul, timer, Lihat semua di kanan). --}}
        <div class="mb-3 flex flex-wrap items-center gap-x-5 gap-y-2.5 sm:mb-5">
            <h2 id="flash-title" class="order-1 text-lg font-bold sm:text-2xl">Flash Sale</h2>
            <a href="{{ route('products.index', ['flash' => 1]) }}" class="order-2 ml-auto inline-flex min-h-10 items-center rounded-full border border-copper-200/40 px-4 text-sm text-copper-100 transition hover:bg-white/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-copper-200 sm:order-3">Lihat semua</a>
            <div class="order-3 flex basis-full items-center gap-2 sm:order-2 sm:basis-auto" role="timer" aria-label="Sisa waktu promo">
                <span class="text-xs text-copper-200/80">Berakhir dalam</span>
                <span x-show="d > 0" x-cloak class="text-sm font-semibold text-copper-200" x-text="d + ' hari'"></span>
                <span class="flex items-center gap-1 font-bold tabular-nums text-[#2b1c14]">
                    <span class="min-w-8 rounded-lg bg-[#f3e3cf] px-1.5 py-1 text-center text-sm" x-text="h">--</span><span class="text-copper-200">:</span>
                    <span class="min-w-8 rounded-lg bg-[#f3e3cf] px-1.5 py-1 text-center text-sm" x-text="m">--</span><span class="text-copper-200">:</span>
                    <span class="min-w-8 rounded-lg bg-[#f3e3cf] px-1.5 py-1 text-center text-sm" x-text="s">--</span>
                </span>
            </div>
        </div>

        @if($show['name'])<p class="mb-3 truncate text-sm text-copper-100/80 sm:mb-4">{{ $show['name'] }}</p>@endif

        <div class="grid grid-cols-2 gap-2.5 sm:-mx-1 sm:flex sm:snap-x sm:gap-4 sm:overflow-x-auto sm:px-1 sm:pb-2">
            @foreach($items as $item)
                @php
                    $p = $item->product;
                    $img = $p->images->first();
                    $soldN = (int) $item->sold;
                    $left = (int) $item->remaining;
                    $bar = $item->quota > 0 ? min(100, (int) round($soldN / $item->quota * 100)) : 0;
                    $out = $left <= 0 || $p->stock <= 0;
                @endphp
                <a href="{{ route('products.show', $p->slug) }}" class="group relative {{ $loop->index >= 4 ? 'max-sm:hidden' : '' }} flex min-w-0 flex-col overflow-hidden rounded-xl bg-white text-stone-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-copper-300 sm:w-[calc(25%-12px)] sm:shrink-0 sm:snap-start sm:rounded-2xl {{ $out ? 'opacity-60' : '' }}">
                    {{-- overflow-hidden + img absolute: tanpa ini foto tinggi memelarkan kotak persegi dan badge ikut bergeser. --}}
                    <div class="relative aspect-square overflow-hidden bg-stone-50">
                        @if($img)
                            <img src="{{ thumb_url($img->image) }}" alt="{{ $p->name }}" loading="lazy" decoding="async" onerror="this.onerror=null;this.src='{{ media_url($img->image) }}'" class="absolute inset-0 h-full w-full object-contain p-3 transition group-hover:scale-[1.03]">
                        @endif
                        <span class="absolute left-2 top-2 rounded-full bg-copper-500 px-2 py-0.5 text-[11px] font-bold text-white sm:left-3 sm:top-3 sm:px-2.5 sm:py-1 sm:text-xs">-{{ $item->discountPercent() }}%</span>
                        @if($out)
                            <span class="absolute inset-0 grid place-items-center bg-white/70 text-sm font-semibold text-stone-700">Habis</span>
                        @elseif($left <= 5)
                            <span class="absolute bottom-2 left-2 rounded-full bg-[#2b1c14] px-2 py-0.5 text-[11px] text-copper-200 sm:bottom-3 sm:left-3">Tinggal {{ $left }}</span>
                        @endif
                    </div>
                    <div class="flex flex-1 flex-col p-3 sm:p-4">
                        @if($p->brand)<span class="truncate text-xs uppercase tracking-wide text-stone-500">{{ $p->brand->name }}</span>@endif
                        <span class="mt-0.5 line-clamp-1 text-[13px] font-medium leading-[1.3] sm:line-clamp-2 sm:text-sm sm:leading-snug" title="{{ $p->name }}">{{ $p->short_name }}</span>
                        <div class="mt-2 flex flex-wrap items-baseline gap-x-2">
                            <span class="text-[15px] font-bold text-copper-700 sm:text-base">Rp {{ number_format($item->sale_price, 0, ',', '.') }}</span>
                            <span class="text-xs text-stone-400 line-through">Rp {{ number_format($p->price, 0, ',', '.') }}</span>
                        </div>
                        <div class="mt-auto pt-3" role="progressbar" aria-valuemin="0" aria-valuemax="{{ $item->quota }}" aria-valuenow="{{ $soldN }}" aria-label="Terjual {{ $soldN }} dari {{ $item->quota }}">
                            <div class="h-1.5 overflow-hidden rounded-full bg-copper-100"><div class="h-full rounded-full bg-copper-500" style="width: {{ $bar }}%"></div></div>
                            <p class="mt-1 text-xs text-stone-500">Terjual {{ $soldN }} dari {{ $item->quota }}</p>
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
