@extends('layouts.admin')

@section('title', 'Users')

@section('content')

<div class="flex flex-wrap justify-between items-center gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-bold text-stone-900">User & Akses</h1>
        <p class="text-sm text-stone-500 mt-1">Akun staf, admin, dan customer.</p>
    </div>
    <a href="{{ route('admin.users.create') }}"
       class="bg-orange-500 hover:bg-orange-600 text-white px-5 py-2.5 rounded-lg font-medium transition">+ Tambah User</a>
</div>

<div class="grid grid-cols-3 gap-3 mb-6">
    <div class="bg-white p-4 rounded-lg border border-stone-200"><div class="text-xs text-stone-500">Total</div><div class="text-2xl font-bold mt-1">{{ $stats['total'] }}</div></div>
    <div class="bg-white p-4 rounded-lg border border-stone-200"><div class="text-xs text-stone-500">Tim Internal</div><div class="text-2xl font-bold mt-1">{{ $stats['staff'] }}</div></div>
    <div class="bg-white p-4 rounded-lg border border-stone-200"><div class="text-xs text-stone-500">Customer</div><div class="text-2xl font-bold mt-1">{{ $stats['customer'] }}</div></div>
</div>

<form method="GET" class="bg-white p-4 rounded-lg border border-stone-200 mb-6 flex flex-wrap gap-3">
    <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari nama / email"
           class="flex-1 min-w-[200px] border border-stone-300 rounded-lg px-3 py-2">
    <select name="role" class="border border-stone-300 rounded-lg px-3 py-2">
        <option value="">Semua Role</option>
        @foreach($roles as $r)
            <option value="{{ $r }}" @selected(request('role') === $r)>{{ $r }}</option>
        @endforeach
    </select>
    <button class="bg-stone-900 text-white px-5 py-2 rounded-lg">Filter</button>
</form>

<div class="bg-white rounded-lg border border-stone-200 overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-stone-50 text-left text-stone-500">
            <tr><th class="px-4 py-3">Nama</th><th class="px-4 py-3">Email</th><th class="px-4 py-3">Role</th><th class="px-4 py-3">Bergabung</th><th class="px-4 py-3"></th></tr>
        </thead>
        <tbody class="divide-y divide-stone-100">
        @forelse($users as $u)
            <tr>
                <td class="px-4 py-3 font-medium">{{ $u->name }} @if($u->id === auth()->id())<span class="text-xs text-stone-400">(kamu)</span>@endif</td>
                <td class="px-4 py-3 text-stone-600">{{ $u->email }}</td>
                <td class="px-4 py-3">
                    @forelse($u->roles as $r)
                        <span class="px-2 py-0.5 rounded bg-stone-100 text-stone-700 text-xs">{{ $r->name }}</span>
                    @empty
                        <span class="text-xs text-stone-400">tanpa role</span>
                    @endforelse
                </td>
                <td class="px-4 py-3 text-stone-500">{{ $u->created_at->format('d M Y') }}</td>
                <td class="px-4 py-3 text-right whitespace-nowrap">
                    <a href="{{ route('admin.users.edit', $u->id) }}" class="text-orange-600 hover:underline mr-3">Edit</a>
                    @if($u->id !== auth()->id())
                    <form method="POST" action="{{ route('admin.users.destroy', $u->id) }}" class="inline"
                          onsubmit="return confirm('Hapus user {{ $u->name }}?')">
                        @csrf @method('DELETE')
                        <button class="text-red-600 hover:underline">Hapus</button>
                    </form>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="px-4 py-10 text-center text-stone-500">Tidak ada user.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $users->links() }}</div>

@endsection
