@extends('layouts.admin')

@section('title', 'Activity Log')

@section('content')

<div class="mb-6">
    <h1 class="text-2xl font-bold text-stone-900">Activity Log</h1>
    <p class="text-sm text-stone-500 mt-1">Jejak siapa mengubah apa di sistem.</p>
</div>

<form method="GET" class="bg-white p-4 rounded-lg border border-stone-200 mb-6 flex flex-wrap gap-3">
    <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari deskripsi"
           class="flex-1 min-w-[180px] border border-stone-300 rounded-lg px-3 py-2">
    <select name="log" class="border border-stone-300 rounded-lg px-3 py-2">
        <option value="">Semua Modul</option>
        @foreach($logNames as $l)
            <option value="{{ $l }}" @selected(request('log') === $l)>{{ $l }}</option>
        @endforeach
    </select>
    <select name="causer" class="border border-stone-300 rounded-lg px-3 py-2">
        <option value="">Semua Pelaku</option>
        <option value="system" @selected(request('causer') === 'system')>Sistem (otomatis)</option>
        @foreach($causers as $c)
            <option value="{{ $c->id }}" @selected((string) request('causer') === (string) $c->id)>{{ $c->name }}</option>
        @endforeach
    </select>
    <input type="date" name="from" value="{{ request('from') }}" class="border border-stone-300 rounded-lg px-3 py-2">
    <input type="date" name="to" value="{{ request('to') }}" class="border border-stone-300 rounded-lg px-3 py-2">
    <button class="bg-stone-900 text-white px-5 py-2 rounded-lg">Filter</button>
</form>

<div class="bg-white rounded-lg border border-stone-200 overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-stone-50 text-left text-stone-500">
            <tr><th class="px-4 py-3">Waktu</th><th class="px-4 py-3">Pelaku</th><th class="px-4 py-3">Modul</th><th class="px-4 py-3">Aktivitas</th><th class="px-4 py-3">Perubahan</th></tr>
        </thead>
        <tbody class="divide-y divide-stone-100">
        @forelse($activities as $a)
            @php
                // v5: perubahan atribut model ada di attribute_changes; data manual (role/izin) di properties.
                $props = collect($a->properties)->toArray();
                $changes = collect($a->attribute_changes)->toArray();
                $old = $changes['old'] ?? ($props['old'] ?? []);
                $new = $changes['attributes'] ?? ($props['attributes'] ?? []);
            @endphp
            <tr class="align-top">
                <td class="px-4 py-3 whitespace-nowrap text-stone-500">{{ $a->created_at->format('d M Y H:i') }}</td>
                <td class="px-4 py-3">{{ $a->causer->name ?? 'Sistem' }}</td>
                <td class="px-4 py-3"><span class="px-2 py-0.5 rounded bg-stone-100 text-xs">{{ $a->log_name }}</span></td>
                <td class="px-4 py-3">
                    {{ $a->description }}
                    @if($a->subject_id)<div class="text-xs text-stone-400">#{{ $a->subject_id }}</div>@endif
                </td>
                <td class="px-4 py-3 text-xs text-stone-600">
                    @forelse($new as $key => $val)
                        <div><span class="text-stone-400">{{ $key }}:</span>
                            @if(array_key_exists($key, $old))<span class="line-through text-stone-400">{{ is_scalar($old[$key]) || is_null($old[$key]) ? $old[$key] : json_encode($old[$key]) }}</span> → @endif
                            {{ is_scalar($val) || is_null($val) ? $val : json_encode($val) }}
                        </div>
                    @empty
                        @if(isset($props['added']) || isset($props['removed']))
                            @if(!empty($props['added']))<div class="text-green-700">+ {{ implode(', ', $props['added']) }}</div>@endif
                            @if(!empty($props['removed']))<div class="text-red-700">− {{ implode(', ', $props['removed']) }}</div>@endif
                        @else
                            -
                        @endif
                    @endforelse
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="px-4 py-10 text-center text-stone-500">Belum ada aktivitas tercatat.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $activities->links() }}</div>

@endsection
