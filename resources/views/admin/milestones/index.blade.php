@extends('layouts.admin')

@section('title', 'Milestone')

@section('content')
<div class="dash">
    <header class="dash-head">
        <div>
            <p class="dash-eyebrow">Konten</p>
            <h1 class="dash-h1">Milestone</h1>
            <p class="dash-lede">Garis perjalanan berbentuk S di beranda (4 pertama) dan halaman Tentang (semua). Tulis hanya pencapaian yang benar-benar terjadi.</p>
        </div>
        @can('create_milestones')<a href="{{ route('admin.milestones.create') }}" class="pay-btn is-primary !px-5 !py-2.5">+ Tambah milestone</a>@endcan
    </header>

    <section class="dash-card">
        <div class="dash-tablewrap overflow-x-auto" style="margin-top:-.25rem;border-top:0">
            <table class="dash-table">
                <thead><tr><th class="text-right">Urutan</th><th>Tahun / periode</th><th>Milestone</th><th>Status</th><th class="text-right">Aksi</th></tr></thead>
                <tbody>
                    @forelse($milestones as $m)
                        <tr>
                            <td class="text-right tabular-nums text-stone-500">{{ $m->sort_order }}</td>
                            <td class="font-medium">{{ $m->year }}</td>
                            <td class="max-w-lg whitespace-normal"><p class="font-medium text-stone-900">{{ $m->title }}</p><p class="text-xs text-stone-500">{{ \Illuminate\Support\Str::limit($m->description, 110) }}</p></td>
                            <td><span class="dash-badge" style="--c: {{ $m->is_active ? '#5b7f4b' : '#a8998b' }}"><i></i>{{ $m->is_active ? 'Tampil' : 'Disembunyikan' }}</span></td>
                            <td class="text-right">
                                <div class="flex items-center justify-end gap-2">
                                    @can('edit_milestones')<a href="{{ route('admin.milestones.edit', $m->id) }}" class="pay-btn is-primary !py-1.5">Edit</a>@endcan
                                    @can('delete_milestones')<form method="POST" action="{{ route('admin.milestones.destroy', $m->id) }}" onsubmit="return confirm('Hapus milestone ini?')">@csrf @method('DELETE')<button class="pay-btn is-quiet !py-1.5 !text-red-700">Hapus</button></form>@endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-14 text-center text-stone-500">Belum ada milestone. Bagian ini tidak tampil di situs sampai ada isinya.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection
