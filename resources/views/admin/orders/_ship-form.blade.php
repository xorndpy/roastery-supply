{{-- Form input kurir & resi. Dipakai di detail pesanan dan antrean pengiriman. --}}
@php
    $compact = $compact ?? false;
    $guess = \App\Support\Couriers::guess($order->shipping_method);
    $mine = (string) old('ship_order') === (string) $order->id;
    // Isian gudang yang sudah tersimpan (belum dikirim) tampil kembali saat form dibuka.
    $draft = $order->shipment && $order->shipment->status === 'pending' ? $order->shipment : null;
    $draftCourier = $draft?->courier;
    $draftKnown = $draftCourier && in_array($draftCourier, \App\Support\Couriers::options(), true);
    $selected = $mine ? old('courier') : ($draftCourier ? ($draftKnown ? $draftCourier : '__other') : $guess);
    $fieldCls = 'w-full border border-stone-300 rounded-lg px-3 py-2 text-sm';
@endphp
<form method="POST" action="{{ route('admin.orders.prepare-shipment', $order->id) }}"
      class="{{ $compact ? 'flex flex-wrap items-start gap-2' : 'mb-3 space-y-3' }}"
      x-data="{ c: @js($selected ?? '') }">
    @csrf
    <input type="hidden" name="ship_order" value="{{ $order->id }}">

    @if(! $compact && $order->shipping_method)
        <p class="rounded-lg bg-amber-50 border border-amber-200 px-3 py-2 text-xs text-amber-900">
            Pelanggan memilih: <strong>{{ $order->shipping_method }}</strong>{{ $order->shipping_etd ? ' · estimasi ' . $order->shipping_etd : '' }}
        </p>
    @endif

    <div class="{{ $compact ? 'w-40' : '' }}">
        @unless($compact)<label class="block text-sm font-medium mb-1">Kurir *</label>@endunless
        <select name="courier" x-model="c" required class="{{ $fieldCls }}">
            <option value="">Pilih kurir…</option>
            @foreach(\App\Support\Couriers::options() as $name)
                <option value="{{ $name }}" @selected($selected === $name)>{{ $name }}</option>
            @endforeach
            <option value="__other" @selected($selected === '__other')>Lainnya…</option>
        </select>
        <input type="text" name="courier_other" value="{{ $mine ? old('courier_other') : ($draftCourier && !$draftKnown ? $draftCourier : '') }}" placeholder="Nama kurir"
               x-show="c === '__other'" :required="c === '__other'" @if($selected !== '__other') style="display:none" @endif
               class="{{ $fieldCls }} mt-2">
    </div>

    <div class="{{ $compact ? 'w-28' : '' }}">
        @unless($compact)<label class="block text-sm font-medium mb-1">Layanan <span class="text-stone-400 font-normal">(opsional)</span></label>@endunless
        <input type="text" name="service" value="{{ $mine ? old('service') : ($draft?->service ?? '') }}" placeholder="REG, YES…" maxlength="100" class="{{ $fieldCls }}">
    </div>

    <div class="{{ $compact ? 'w-48' : '' }}">
        @unless($compact)<label class="block text-sm font-medium mb-1">No. Resi *</label>@endunless
        <input type="text" name="tracking_number" value="{{ $mine ? old('tracking_number') : ($draft?->tracking_number ?? '') }}" required maxlength="60"
               placeholder="Tempel nomor resi" autocomplete="off" class="{{ $fieldCls }} font-mono">
    </div>

    <button type="submit"
            class="{{ $compact ? 'border border-stone-300 bg-white hover:bg-stone-50 text-stone-800 px-4 py-2 rounded-lg text-sm' : 'w-full border border-stone-300 bg-white hover:bg-stone-50 text-stone-800 py-3 rounded-lg font-medium transition' }}">
        {{ $draft ? 'Perbarui kurir & resi' : 'Simpan kurir & resi' }}
    </button>

    @if($mine && $errors->any())
        <div class="{{ $compact ? 'basis-full' : '' }} rounded-lg bg-red-50 border border-red-200 px-3 py-2 text-xs text-red-700">
            @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
        </div>
    @endif
</form>
