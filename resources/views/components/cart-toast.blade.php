@php $added = session('cart_added'); @endphp
@if($added)
<style>
    .ctoast { position: fixed; top: 76px; right: 16px; z-index: 70; width: min(360px, calc(100vw - 32px)); padding: 14px 16px; border: 1px solid #d9e7de; border-radius: 14px; background: #fff; box-shadow: 0 12px 32px -8px rgb(24 16 12 / .25); font-size: 14px; color: #1c1917; }
    .ctoast-h { display: flex; gap: 10px; align-items: flex-start; }
    .ctoast-ok { flex: none; display: grid; place-items: center; width: 22px; height: 22px; border-radius: 999px; background: #16a34a; color: #fff; font-size: 13px; line-height: 1; }
    .ctoast-t { flex: 1; min-width: 0; }
    .ctoast-t b { display: block; font-weight: 600; }
    .ctoast-t span { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; color: #57534e; font-size: 13px; }
    .ctoast-x { flex: none; border: 0; background: transparent; color: #78716c; font-size: 18px; line-height: 1; cursor: pointer; padding: 0 2px; }
    .ctoast-a { display: flex; gap: 8px; margin-top: 12px; }
    .ctoast-a a, .ctoast-a button { flex: 1; text-align: center; padding: 9px 10px; border-radius: 10px; font-size: 13.5px; font-weight: 600; cursor: pointer; text-decoration: none; }
    .ctoast-p { background: #4a2f21; color: #fff; border: 1px solid #4a2f21; }
    .ctoast-s { background: #fff; color: #4a2f21; border: 1px solid #d6c3b6; }
    .ctoast a:focus-visible, .ctoast button:focus-visible { outline: 2px solid #a8643a; outline-offset: 2px; }
</style>
<div class="ctoast" id="ctoast" role="status" aria-live="polite">
    <div class="ctoast-h">
        <span class="ctoast-ok" aria-hidden="true">&#10003;</span>
        <div class="ctoast-t">
            <b>Ditambahkan ke keranjang</b>
            <span>{{ $added['name'] }} · {{ $added['qty'] }} unit di keranjang</span>
        </div>
        <button type="button" class="ctoast-x" aria-label="Tutup" onclick="document.getElementById('ctoast').remove()">&times;</button>
    </div>
    <div class="ctoast-a">
        <a href="{{ route('customer.cart.index') }}" class="ctoast-p">Lihat keranjang</a>
        <button type="button" class="ctoast-s" onclick="document.getElementById('ctoast').remove()">Lanjut belanja</button>
    </div>
</div>
@endif
