@extends('layouts.app')

@section('title', 'Ajukan Retur')

@section('content')
<div class="max-w-2xl mx-auto px-4 sm:px-6 py-10">

    <a href="{{ route('customer.returns.index') }}" class="text-sm text-stone-500 hover:text-stone-700">← Retur Saya</a>
    <h1 class="text-3xl font-bold mt-2 mb-6">Ajukan Retur</h1>

    @if(session('error'))<div class="mb-4 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg">{{ session('error') }}</div>@endif

    @if(!$order)
        <p class="text-stone-600 mb-4">Pilih pesanan yang ingin diretur:</p>
        <div class="bg-white rounded-lg border border-stone-200 divide-y divide-stone-100">
            @forelse($eligible as $o)
                <a href="{{ route('customer.returns.create', $o->order_number) }}" class="flex flex-wrap justify-between gap-2 p-5 hover:bg-stone-50">
                    <div>
                        <div class="font-mono font-medium">{{ $o->order_number }}</div>
                        <div class="text-sm text-stone-500">{{ $o->items->pluck('product_name')->implode(', ') }}</div>
                    </div>
                    <div class="text-sm text-stone-500">Batas retur {{ $o->return_deadline->format('d M Y') }}</div>
                </a>
            @empty
                <div class="p-8 text-center text-stone-500">Tidak ada pesanan yang masih bisa diretur.<br>
                    <span class="text-sm">Retur hanya untuk pesanan selesai, maksimal {{ \App\Services\ReturnService::WINDOW_DAYS }} hari sejak selesai.</span></div>
            @endforelse
        </div>
    @else
        <div class="mb-6 p-4 bg-stone-100 rounded-lg text-sm">
            Pesanan <strong class="font-mono">{{ $order->order_number }}</strong> ·
            batas pengajuan <strong>{{ $order->return_deadline->format('d M Y H:i') }}</strong>
        </div>

        @if ($errors->any())
            <div class="mb-4 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm"><ul class="list-disc list-inside">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
        @endif

        <form method="POST" action="{{ route('customer.returns.store') }}" enctype="multipart/form-data" class="bg-white p-6 rounded-lg border border-stone-200 space-y-5">
            @csrf
            <input type="hidden" name="order_number" value="{{ $order->order_number }}">

            <div>
                <label class="block text-sm font-medium mb-1.5">Alasan retur *</label>
                <select name="reason_type" required class="w-full border border-stone-300 rounded-lg px-3 py-2">
                    @foreach($reasonTypes as $key => $label)<option value="{{ $key }}" @selected(old('reason_type') === $key)>{{ $label }}</option>@endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium mb-1.5">Jelaskan masalahnya *</label>
                <textarea name="reason" rows="4" required minlength="10" maxlength="1000" class="w-full border border-stone-300 rounded-lg px-3 py-2">{{ old('reason') }}</textarea>
            </div>

            <div>
                <label class="block text-sm font-medium mb-1.5">Foto bukti * <span class="font-normal text-stone-400">(1–3 foto, JPG/PNG/WEBP, maks 4 MB)</span></label>
                <input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple required class="w-full border border-stone-300 rounded-lg px-3 py-2 text-sm">
            </div>

            <fieldset class="border-t border-stone-100 pt-5">
                <legend class="font-medium mb-1">Rekening untuk refund</legend>
                <p class="text-xs text-stone-500 mb-3">Dana dikembalikan lewat transfer bank jika retur disetujui.</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div><label class="block text-sm mb-1.5">Bank *</label><input type="text" name="refund_bank" value="{{ old('refund_bank') }}" required maxlength="50" class="w-full border border-stone-300 rounded-lg px-3 py-2"></div>
                    <div><label class="block text-sm mb-1.5">Nomor rekening *</label><input type="text" inputmode="numeric" name="refund_account_number" value="{{ old('refund_account_number') }}" required class="w-full border border-stone-300 rounded-lg px-3 py-2"></div>
                    <div class="sm:col-span-2"><label class="block text-sm mb-1.5">Atas nama *</label><input type="text" name="refund_account_name" value="{{ old('refund_account_name') }}" required maxlength="100" class="w-full border border-stone-300 rounded-lg px-3 py-2"></div>
                </div>
            </fieldset>

            <button class="bg-orange-500 hover:bg-orange-600 text-white px-6 py-3 rounded-lg font-medium">Kirim Pengajuan</button>
            <p class="text-xs text-stone-500">Setiap pesanan hanya bisa diajukan retur satu kali.</p>
        </form>
    @endif
</div>
@endsection
