@extends('layouts.admin')

@section('title', 'Pembayaran')

@section('content')

<div class="flex flex-wrap justify-between items-center gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-bold text-stone-900">Verifikasi Pembayaran</h1>
        <p class="text-sm text-stone-500 mt-1">Kelola bukti pembayaran dari customer.</p>
    </div>
</div>

{{-- Stats --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
    <a href="{{ route('admin.payments.index', ['status' => 'pending']) }}"
       class="bg-white p-4 rounded-lg border-2 {{ request('status') === 'pending' ? 'border-orange-500' : 'border-stone-200' }} hover:border-orange-500 transition">
        <div class="flex items-center justify-between mb-1">
            <div class="text-xs text-stone-500">Menunggu Verifikasi</div>
            <div class="text-lg">⏳</div>
        </div>
        <div class="text-2xl font-bold text-orange-600">{{ $stats['pending'] }}</div>
    </a>
    <a href="{{ route('admin.payments.index', ['status' => 'verified']) }}"
       class="bg-white p-4 rounded-lg border-2 {{ request('status') === 'verified' ? 'border-green-500' : 'border-stone-200' }} hover:border-green-500 transition">
        <div class="flex items-center justify-between mb-1">
            <div class="text-xs text-stone-500">Terverifikasi</div>
            <div class="text-lg">✅</div>
        </div>
        <div class="text-2xl font-bold text-green-600">{{ $stats['verified'] }}</div>
    </a>
    <a href="{{ route('admin.payments.index', ['status' => 'rejected']) }}"
       class="bg-white p-4 rounded-lg border-2 {{ request('status') === 'rejected' ? 'border-red-500' : 'border-stone-200' }} hover:border-red-500 transition">
        <div class="flex items-center justify-between mb-1">
            <div class="text-xs text-stone-500">Ditolak</div>
            <div class="text-lg">❌</div>
        </div>
        <div class="text-2xl font-bold text-red-600">{{ $stats['rejected'] }}</div>
    </a>
    <div class="bg-white p-4 rounded-lg border border-stone-200">
        <div class="flex items-center justify-between mb-1">
            <div class="text-xs text-stone-500">Total Pending</div>
            <div class="text-lg">💰</div>
        </div>
        <div class="text-lg font-bold text-stone-900">
            Rp {{ number_format($stats['total_pending_amount'], 0, ',', '.') }}
        </div>
    </div>
</div>

{{-- Filter --}}
<div class="bg-white p-4 rounded-lg border border-stone-200 mb-6">
    <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-3">
        <input type="text" name="search" value="{{ request('search') }}"
               placeholder="Cari no. order / nama customer..."
               class="md:col-span-2 border border-stone-300 rounded-lg px-3 py-2 text-sm">

        <select name="status" class="border border-stone-300 rounded-lg px-3 py-2 text-sm">
            <option value="">Semua Status</option>
            <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Menunggu</option>
            <option value="verified" {{ request('status') == 'verified' ? 'selected' : '' }}>Terverifikasi</option>
            <option value="rejected" {{ request('status') == 'rejected' ? 'selected' : '' }}>Ditolak</option>
        </select>

        <input type="date" name="from" value="{{ request('from') }}"
               class="border border-stone-300 rounded-lg px-3 py-2 text-sm">

        <div class="flex gap-2">
            <button type="submit" class="flex-1 bg-stone-800 hover:bg-stone-900 text-white px-4 py-2 rounded-lg text-sm font-medium">
                Filter
            </button>
            <a href="{{ route('admin.payments.index') }}"
               class="px-4 py-2 border border-stone-300 rounded-lg text-sm hover:bg-stone-50">
                Reset
            </a>
        </div>
    </form>
</div>

{{-- Table --}}
<div class="bg-white rounded-lg border border-stone-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-stone-50 border-b border-stone-200">
                <tr>
                    <th class="px-4 py-3 text-left font-medium text-stone-600 w-20">Bukti</th>
                    <th class="px-4 py-3 text-left font-medium text-stone-600">Order</th>
                    <th class="px-4 py-3 text-left font-medium text-stone-600">Customer</th>
                    <th class="px-4 py-3 text-left font-medium text-stone-600">Metode</th>
                    <th class="px-4 py-3 text-right font-medium text-stone-600">Jumlah</th>
                    <th class="px-4 py-3 text-center font-medium text-stone-600">Status</th>
                    <th class="px-4 py-3 text-right font-medium text-stone-600">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-200">
                @forelse($payments as $payment)
                    <tr class="hover:bg-stone-50">
                        <td class="px-4 py-3">
                            <div class="w-12 h-12 rounded bg-stone-100 overflow-hidden border border-stone-200">
                                @if($payment->proof_image)
                                    <a href="{{ asset('storage/' . $payment->proof_image) }}" target="_blank">
                                        <img src="{{ asset('storage/' . $payment->proof_image) }}"
                                             alt="Bukti"
                                             class="w-full h-full object-cover hover:scale-110 transition">
                                    </a>
                                @else
                                    <div class="w-full h-full flex items-center justify-center text-stone-400 text-xs">N/A</div>
                                @endif
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <a href="{{ route('admin.orders.show', $payment->order_id) }}"
                               class="font-medium text-blue-600 hover:text-blue-800">
                                {{ $payment->order->order_number ?? '-' }}
                            </a>
                            <div class="text-xs text-stone-500">{{ $payment->created_at->format('d M Y, H:i') }}</div>
                        </td>
                        <td class="px-4 py-3">
                            <div class="font-medium">{{ $payment->order->customer->name ?? 'Guest' }}</div>
                            <div class="text-xs text-stone-500">{{ $payment->order->customer->phone ?? '-' }}</div>
                        </td>
                        <td class="px-4 py-3 text-stone-600 text-xs">
                            {{ str_replace('_', ' ', strtoupper($payment->method)) }}
                        </td>
                        <td class="px-4 py-3 text-right font-medium">
                            Rp {{ number_format($payment->amount, 0, ',', '.') }}
                        </td>
                        <td class="px-4 py-3 text-center">
                            @php
                                $badges = [
                                    'pending' => 'bg-yellow-100 text-yellow-800',
                                    'verified' => 'bg-green-100 text-green-800',
                                    'rejected' => 'bg-red-100 text-red-800',
                                ];
                                $labels = [
                                    'pending' => 'Menunggu',
                                    'verified' => 'Terverifikasi',
                                    'rejected' => 'Ditolak',
                                ];
                            @endphp
                            <span class="text-xs px-2 py-1 rounded-full {{ $badges[$payment->status] ?? 'bg-stone-100' }}">
                                {{ $labels[$payment->status] ?? $payment->status }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-1">
                                @if($payment->status === 'pending')
                                    <form method="POST" action="{{ route('admin.payments.verify', $payment->id) }}" class="inline">
                                        @csrf
                                        <button type="submit"
                                                onclick="return confirm('Verify pembayaran ini?')"
                                                class="text-xs bg-green-500 hover:bg-green-600 text-white px-3 py-1.5 rounded font-medium">
                                            ✓ Verify
                                        </button>
                                    </form>
                                    <button type="button"
                                            onclick="openRejectModal({{ $payment->id }})"
                                            class="text-xs bg-red-500 hover:bg-red-600 text-white px-3 py-1.5 rounded font-medium">
                                        ✕ Reject
                                    </button>
                                @endif
                                <a href="{{ route('admin.payments.show', $payment->id) }}"
                                   class="text-xs text-blue-600 hover:text-blue-800 font-medium">
                                    Detail
                                </a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-16 text-center text-stone-500">
                            <div class="text-4xl mb-3">💳</div>
                            <p>Belum ada pembayaran.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-4">
    {{ $payments->links() }}
</div>

{{-- Reject Modal --}}
<div id="rejectModal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center">
    <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
        <h3 class="font-bold text-lg mb-3">Tolak Pembayaran</h3>
        <form method="POST" id="rejectForm">
            @csrf
            <div class="mb-4">
                <label class="block text-sm font-medium mb-1.5">Alasan Penolakan</label>
                <textarea name="note" rows="3" required
                          class="w-full border border-stone-300 rounded-lg px-3 py-2 text-sm"
                          placeholder="Contoh: Bukti transfer tidak jelas, nominal tidak sesuai, dll."></textarea>
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" onclick="closeRejectModal()"
                        class="px-4 py-2 border border-stone-300 rounded-lg text-sm hover:bg-stone-50">
                    Batal
                </button>
                <button type="submit"
                        class="px-4 py-2 bg-red-500 hover:bg-red-600 text-white rounded-lg text-sm font-medium">
                    Tolak Pembayaran
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    function openRejectModal(paymentId) {
        const modal = document.getElementById('rejectModal');
        const form = document.getElementById('rejectForm');
        form.action = `/admin/payments/${paymentId}/reject`;
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function closeRejectModal() {
        const modal = document.getElementById('rejectModal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }
</script>
@endpush

@endsection