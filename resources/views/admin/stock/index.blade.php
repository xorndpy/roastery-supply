@extends('layouts.admin')

@section('title', 'Stok')

@section('content')

<div class="flex flex-wrap justify-between items-center gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-bold text-stone-900">Manajemen Stok</h1>
        <p class="text-sm text-stone-500 mt-1">Semua perubahan stok tercatat di riwayat.</p>
    </div>
    <a href="{{ route('admin.stock.history') }}"
       class="border border-stone-300 hover:bg-stone-50 px-5 py-2.5 rounded-lg font-medium transition">
        Riwayat Stok
    </a>
</div>

<div class="grid grid-cols-3 gap-3 mb-6">
    <a href="{{ route('admin.stock.index') }}" class="bg-white p-4 rounded-lg border border-stone-200">
        <div class="text-xs text-stone-500">Total Produk</div>
        <div class="text-2xl font-bold mt-1">{{ $stats['total'] }}</div>
    </a>
    <a href="{{ route('admin.stock.index', ['status' => 'low']) }}" class="bg-white p-4 rounded-lg border border-stone-200">
        <div class="text-xs text-stone-500">Stok Menipis</div>
        <div class="text-2xl font-bold text-amber-600 mt-1">{{ $stats['low'] }}</div>
    </a>
    <a href="{{ route('admin.stock.index', ['status' => 'out']) }}" class="bg-white p-4 rounded-lg border border-stone-200">
        <div class="text-xs text-stone-500">Stok Habis</div>
        <div class="text-2xl font-bold text-red-600 mt-1">{{ $stats['out'] }}</div>
    </a>
</div>

<form method="GET" class="bg-white p-4 rounded-lg border border-stone-200 mb-6 flex flex-wrap gap-3">
    <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari nama / SKU"
           class="flex-1 min-w-[200px] border border-stone-300 rounded-lg px-3 py-2">
    <select name="category_id" class="border border-stone-300 rounded-lg px-3 py-2">
        <option value="">Semua Kategori</option>
        @foreach($categories as $c)
            <option value="{{ $c->id }}" @selected(request('category_id') == $c->id)>{{ $c->name }}</option>
        @endforeach
    </select>
    <select name="status" class="border border-stone-300 rounded-lg px-3 py-2">
        <option value="">Semua Status</option>
        <option value="out" @selected(request('status') === 'out')>Habis</option>
        <option value="low" @selected(request('status') === 'low')>Menipis</option>
        <option value="ok" @selected(request('status') === 'ok')>Aman</option>
    </select>
    <button class="bg-stone-900 text-white px-5 py-2 rounded-lg">Filter</button>
</form>

<div x-data="stockModal()">

    <div class="bg-white rounded-lg border border-stone-200 overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-stone-50 text-left text-stone-500">
                <tr>
                    <th class="px-4 py-3">Produk</th>
                    <th class="px-4 py-3">SKU</th>
                    <th class="px-4 py-3 text-right">Stok</th>
                    <th class="px-4 py-3 text-right">Batas</th>
                    <th class="px-4 py-3">Status</th>
                    @can('manage_stock')<th class="px-4 py-3 text-right">Aksi</th>@endcan
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
            @forelse($products as $p)
                <tr x-data="inlineStock({ id: {{ $p->id }}, name: @js($p->name), stock: {{ (int) $p->stock }}, threshold: {{ (int) $p->stock_threshold }}, url: @js(route('admin.stock.adjust')) })">
                    <td class="px-4 py-3">
                        <div class="font-medium">{{ $p->name }}</div>
                        <div class="text-xs text-stone-500">{{ $p->category->name ?? '-' }} · {{ $p->brand->name ?? '-' }}</div>
                    </td>
                    <td class="px-4 py-3 font-mono text-xs">{{ $p->sku }}</td>
                    <td class="px-4 py-3 text-right">
                        @can('manage_stock')
                            <button type="button" x-show="!editing" @click="start()" title="Klik untuk ubah stok"
                                    class="font-bold tabular-nums underline decoration-dotted decoration-stone-400 underline-offset-4 hover:decoration-stone-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-stone-400 rounded" x-text="stock">{{ $p->stock }}</button>
                            <form x-show="editing" x-cloak @submit.prevent="save()" @keydown.escape.stop="cancel()" class="inline-flex flex-wrap items-center justify-end gap-1.5" data-no-lock>
                                <input x-ref="n" type="number" min="0" inputmode="numeric" x-model="val" aria-label="Stok baru {{ $p->name }}"
                                       class="w-20 rounded-lg border border-stone-300 px-2 py-1 text-right text-sm">
                                <select x-model="reason" aria-label="Alasan" class="rounded-lg border border-stone-300 px-2 py-1 text-xs">
                                    <optgroup label="Stok bertambah"><option>Restock dari supplier</option><option>Retur pelanggan</option></optgroup>
                                    <optgroup label="Stok berkurang"><option>Rusak</option><option>Sampel</option><option>Hilang</option><option>Retur ke supplier</option></optgroup>
                                    <optgroup label="Koreksi"><option>Stock opname</option><option>Koreksi hitungan</option></optgroup>
                                </select>
                                <button type="submit" :disabled="saving" class="row-btn">Simpan</button>
                                <button type="button" @click="cancel()" class="text-xs text-stone-500 hover:text-stone-800">Batal</button>
                            </form>
                            <p x-show="error" x-cloak x-text="error" class="mt-1 text-xs text-red-700" role="alert"></p>
                        @else
                            <span class="font-bold">{{ $p->stock }}</span>
                        @endcan
                    </td>
                    <td class="px-4 py-3 text-right text-stone-500">{{ $p->stock_threshold }}</td>
                    <td class="px-4 py-3">
                        <span x-show="state === 'out'" @if($p->stock > 0) x-cloak @endif class="px-2 py-0.5 rounded bg-red-100 text-red-700 text-xs">Habis</span>
                        <span x-show="state === 'low'" @if(! ($p->stock > 0 && $p->stock <= $p->stock_threshold)) x-cloak @endif class="px-2 py-0.5 rounded bg-amber-100 text-amber-700 text-xs">Menipis</span>
                        <span x-show="state === 'ok'" @if($p->stock <= $p->stock_threshold) x-cloak @endif class="px-2 py-0.5 rounded bg-stone-100 text-stone-700 text-xs">Aman</span>
                    </td>
                    @can('manage_stock')
                    <td class="px-4 py-3 text-right">
                        <button type="button"
                                @click="openFor({ id: id, name: name, stock: stock })"
                                class="border border-stone-300 hover:border-copper-500 hover:text-copper-700 px-3 py-1.5 rounded-lg text-xs font-medium transition">
                            Atur stok
                        </button>
                    </td>
                    @endcan
                </tr>
            @empty
                <tr><td colspan="6" class="px-4 py-10 text-center text-stone-500">Tidak ada produk.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $products->links() }}</div>

    @can('manage_stock')
    {{-- Dialog atur stok (satu untuk semua baris) --}}
    <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-end sm:items-center justify-center" @keydown.escape.window="open = false">
        <div class="absolute inset-0 bg-black/40" @click="open = false"></div>

        <form method="POST" action="{{ route('admin.stock.adjust') }}"
              class="relative w-full sm:max-w-md bg-white rounded-t-2xl sm:rounded-2xl p-6 space-y-5 shadow-xl max-h-[92vh] overflow-y-auto"
              @submit="if (!valid) $event.preventDefault()">
            @csrf
            <input type="hidden" name="product_id" :value="p.id">
            <input type="hidden" name="type" :value="type">

            <div>
                <h2 class="font-bold text-lg">Atur stok</h2>
                <p class="text-sm text-stone-500 mt-0.5" x-text="p.name"></p>
            </div>

            <div class="grid grid-cols-3 gap-2 text-sm">
                <button type="button" @click="setType('in')" class="rounded-lg border px-2 py-2.5 font-medium transition"
                        :class="type === 'in' ? 'border-green-600 bg-green-50 text-green-800' : 'border-stone-300 text-stone-600 hover:border-stone-400'">
                    Tambah
                </button>
                <button type="button" @click="setType('out')" class="rounded-lg border px-2 py-2.5 font-medium transition"
                        :class="type === 'out' ? 'border-red-600 bg-red-50 text-red-800' : 'border-stone-300 text-stone-600 hover:border-stone-400'">
                    Kurangi
                </button>
                <button type="button" @click="setType('set')" class="rounded-lg border px-2 py-2.5 font-medium transition"
                        :class="type === 'set' ? 'border-copper-600 bg-copper-50 text-copper-800' : 'border-stone-300 text-stone-600 hover:border-stone-400'">
                    Hitung fisik
                </button>
            </div>
            <p class="-mt-2 text-xs text-stone-500" x-text="hint"></p>

            <div>
                <label class="block text-sm font-medium mb-1.5" x-text="type === 'set' ? 'Jumlah hasil hitung fisik' : 'Jumlah'"></label>
                <input x-ref="qty" type="number" name="quantity" min="0" inputmode="numeric" required x-model="qty"
                       class="w-full border border-stone-300 rounded-lg px-3 py-2.5 text-lg focus:border-copper-500 focus:ring-2 focus:ring-copper-500/20">
            </div>

            <div class="rounded-lg bg-stone-50 border border-stone-200 px-4 py-3 text-sm flex items-center justify-between">
                <span class="text-stone-500">Stok sekarang <b class="text-stone-900" x-text="p.stock"></b></span>
                <span>
                    <span class="text-stone-500">Menjadi</span>
                    <b class="text-lg" :class="result === null ? 'text-stone-300' : (result < 0 ? 'text-red-600' : 'text-stone-900')" x-text="result === null ? '–' : result"></b>
                </span>
            </div>
            <p x-show="result !== null && result < 0" x-cloak class="-mt-3 text-xs text-red-600">Stok tidak boleh kurang dari 0.</p>

            <div>
                <label class="block text-sm font-medium mb-1.5">
                    Alasan <span class="text-xs font-normal text-stone-500" x-text="type === 'in' ? '(opsional)' : '(wajib)'"></span>
                </label>
                <input type="text" name="note" maxlength="255" x-model="note" placeholder="Tulis alasan singkat"
                       class="w-full border border-stone-300 rounded-lg px-3 py-2.5 text-sm focus:border-copper-500 focus:ring-2 focus:ring-copper-500/20">
                <div class="flex flex-wrap gap-1.5 mt-2">
                    <template x-for="r in reasons[type]" :key="r">
                        <button type="button" @click="note = r" class="text-xs px-2.5 py-1 rounded-full border border-stone-300 text-stone-600 hover:border-copper-500 hover:text-copper-700" x-text="r"></button>
                    </template>
                </div>
            </div>

            <div class="flex gap-3 pt-1">
                <button type="button" @click="open = false" class="flex-1 border border-stone-300 hover:bg-stone-50 rounded-lg py-2.5 font-medium">Batal</button>
                <button type="submit" :disabled="!valid" class="flex-1 bg-stone-900 hover:bg-stone-800 text-white rounded-lg py-2.5 font-medium disabled:opacity-40 disabled:cursor-not-allowed">Simpan</button>
            </div>
        </form>
    </div>
    @endcan
</div>

@endsection

@push('scripts')
<script>
function inlineStock(c) {
    // kind: in = stok bertambah, out = stok berkurang, set = koreksi/opname (stok diganti)
    const kinds = { 'Restock dari supplier': 'in', 'Retur pelanggan': 'in', 'Rusak': 'out', 'Sampel': 'out', 'Hilang': 'out', 'Retur ke supplier': 'out' };
    return {
        ...c, editing: false, val: '', reason: 'Stock opname', saving: false, error: '',
        get state() { return this.stock <= 0 ? 'out' : (this.stock <= this.threshold ? 'low' : 'ok'); },
        start() { this.error = ''; this.val = this.stock; this.reason = 'Stock opname'; this.editing = true; this.$nextTick(() => { this.$refs.n.focus(); this.$refs.n.select(); }); },
        cancel() { this.editing = false; this.error = ''; },
        async save() {
            const n = parseInt(this.val, 10);
            if (isNaN(n) || n < 0) { this.error = 'Isi angka stok 0 atau lebih.'; return; }
            const d = n - this.stock;
            if (d === 0) { this.cancel(); return; }
            // Arah perubahan menentukan jenis mutasi; bila tidak cocok dengan alasan, dicatat sebagai koreksi.
            let type = 'set', quantity = n; const k = kinds[this.reason];
            if (k === 'in' && d > 0) { type = 'in'; quantity = d; } else if (k === 'out' && d < 0) { type = 'out'; quantity = -d; }
            this.saving = true; this.error = '';
            try {
                const r = await fetch(this.url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                    body: JSON.stringify({ product_id: this.id, type, quantity, note: this.reason }),
                });
                const j = await r.json().catch(() => ({}));
                if (r.ok) { this.stock = j.stock; this.editing = false; }
                else this.error = j.message || (j.errors && Object.values(j.errors)[0][0]) || 'Gagal menyimpan.';
            } catch (e) { this.error = 'Koneksi bermasalah. Coba lagi.'; }
            this.saving = false;
        },
    };
}

function stockModal() {
    return {
        open: false,
        p: { id: null, name: '', stock: 0 },
        type: 'in',
        qty: '',
        note: '',
        reasons: {
            in:  ['Restock dari supplier', 'Retur pelanggan', 'Koreksi hitungan'],
            out: ['Rusak', 'Sampel', 'Hilang', 'Retur ke supplier'],
            set: ['Stock opname'],
        },
        get hint() {
            return {
                in:  'Barang masuk: jumlah ditambahkan ke stok sekarang.',
                out: 'Barang keluar di luar penjualan (rusak, sampel, hilang).',
                set: 'Stock opname: stok diganti sesuai hasil hitung fisik di gudang.',
            }[this.type];
        },
        get result() {
            const q = parseInt(this.qty, 10);
            if (isNaN(q)) return null;
            if (this.type === 'in') return this.p.stock + q;
            if (this.type === 'out') return this.p.stock - q;
            return q;
        },
        get valid() {
            const r = this.result, q = parseInt(this.qty, 10);
            if (r === null || r < 0 || r === this.p.stock) return false;
            if (this.type !== 'set' && q < 1) return false;
            if (this.type !== 'in' && this.note.trim() === '') return false;
            return true;
        },
        openFor(p) {
            this.p = p; this.type = 'in'; this.qty = ''; this.note = ''; this.open = true;
            this.$nextTick(() => this.$refs.qty && this.$refs.qty.focus());
        },
        setType(t) {
            this.type = t;
            this.note = t === 'set' ? 'Stock opname' : '';
        },
    };
}
</script>
@endpush
