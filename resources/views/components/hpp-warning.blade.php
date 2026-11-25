{{-- Peringatan bila ada produk tanpa HPP: laba rugi belum akurat. Hanya tampil untuk yang boleh melihat HPP. --}}
@php $missingHpp = auth()->user()?->can('view_costs') ? \App\Models\Product::whereNull('cost_price')->count() : 0; @endphp
@if($missingHpp > 0)
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="status">
        <p><b>{{ $missingHpp }} produk belum punya HPP.</b> Laba rugi belum akurat sampai HPP-nya diisi.</p>
        <a href="{{ route('admin.finance.costs', ['filter' => 'kosong']) }}" class="shrink-0 rounded-md border border-amber-300 bg-white px-3 py-1.5 font-medium text-amber-900 hover:bg-amber-100">Isi HPP</a>
    </div>
@endif
