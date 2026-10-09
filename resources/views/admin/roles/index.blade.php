@extends('layouts.admin')

@section('title', 'Roles')

@section('content')

<div class="mb-6">
    <h1 class="text-2xl font-bold text-stone-900">Role & Hak Akses</h1>
    <p class="text-sm text-stone-500 mt-1">Atur apa saja yang boleh dilakukan tiap role. Perubahan langsung berlaku.</p>
</div>

<div class="bg-white rounded-lg border border-stone-200 overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-stone-50 text-left text-stone-500">
            <tr><th class="px-4 py-3">Role</th><th class="px-4 py-3 text-right">User</th><th class="px-4 py-3 text-right">Izin</th><th class="px-4 py-3"></th></tr>
        </thead>
        <tbody class="divide-y divide-stone-100">
        @foreach($roles as $r)
            <tr>
                <td class="px-4 py-3 font-medium">{{ $r->name }}</td>
                <td class="px-4 py-3 text-right">{{ $r->users_count }}</td>
                <td class="px-4 py-3 text-right">{{ $r->name === 'super_admin' ? 'Semua' : $r->permissions_count }}</td>
                <td class="px-4 py-3 text-right">
                    <a href="{{ route('admin.roles.edit', $r->id) }}" class="text-orange-600 hover:underline">
                        {{ in_array($r->name, $locked, true) ? 'Lihat' : 'Atur' }}
                    </a>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>

@endsection
