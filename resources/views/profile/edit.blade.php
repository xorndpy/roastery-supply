@extends('layouts.admin')

@section('title', 'Profil')

@section('content')
@php
    $user = $user ?? auth()->user();
    $roleLabel = ['super_admin' => 'Super Admin', 'admin' => 'Admin', 'staff' => 'Staff', 'gudang' => 'Admin Gudang'][$user->getRoleNames()->first()] ?? ucfirst((string) $user->getRoleNames()->first());
    $since = $user->created_at?->copy()->timezone('Asia/Jakarta')->locale('id')->translatedFormat('F Y');
@endphp

<style>
    .pf { --ink:#18181b; --ink-2:#52525b; --muted:#71717a; --rule:#e4e4e7; --accent:#a8643a; --accent-d:#8f5230; max-width: 980px; color: var(--ink); }
    .pf-card { border: 1px solid #ececef; border-radius: 16px; background: #fff; box-shadow: 0 1px 2px rgb(16 24 40 / .03), 0 1px 3px rgb(16 24 40 / .04); }
    .pf-cover { position: relative; height: 150px; border-radius: 16px 16px 0 0; overflow: hidden; background: linear-gradient(120deg, #2b1a10 0%, #4a2f21 55%, #8f5230 120%); }
    .pf-cover svg { position: absolute; inset: 0; width: 100%; height: 100%; opacity: .5; }
    .pf-head { display: flex; flex-wrap: wrap; align-items: flex-end; gap: 1rem 1.25rem; padding: 0 1.75rem 1.5rem; margin-top: -44px; position: relative; }
    .pf-ph { position: relative; flex: none; }
    .pf-ph label { position: absolute; right: -2px; bottom: -2px; display: grid; place-items: center; width: 30px; height: 30px; border-radius: 999px; background: var(--accent); color: #fff; cursor: pointer; box-shadow: 0 0 0 3px #fff; transition: background-color .15s; }
    .pf-ph label:hover { background: var(--accent-d); } .pf-ph label svg { width: 15px; height: 15px; }
    .pf-ph input:focus-visible + label { outline: 2px solid var(--accent); outline-offset: 3px; }
    .pf-name { font-size: 1.375rem; font-weight: 650; letter-spacing: -.02em; line-height: 1.2; }
    .pf-meta { display: flex; flex-wrap: wrap; gap: .4rem .6rem; align-items: center; margin-top: .35rem; font-size: 13px; color: var(--muted); }
    .pf-badge { padding: .1rem .55rem; border-radius: 999px; background: #f6ede6; color: var(--accent-d); font-size: 12px; font-weight: 600; }
    .pf-grid { display: grid; gap: 1.25rem; margin-top: 1.25rem; grid-template-columns: minmax(0, 1fr); }
    @media (min-width: 900px) { .pf-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    .pf-sec { padding: 1.5rem; } .admin-ui .pf .pf-sec h2 { margin: 0; padding: 0; border: 0; font-size: 15px; font-weight: 600; } .pf-sec > p { margin-top: .2rem; font-size: 13px; color: var(--muted); }
    .pf-f { margin-top: 1rem; } .pf-f label { display: block; margin-bottom: .35rem; font-size: 13px; font-weight: 500; color: var(--ink-2); }
    .pf-f input { width: 100%; height: 40px; padding: 0 .8rem; border: 1px solid var(--rule); border-radius: 10px; font-size: 14px; background: #fff; transition: border-color .15s, box-shadow .15s; }
    .pf-f input:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px rgb(168 100 58 / .14); }
    .pf-err { margin-top: .35rem; font-size: 12.5px; color: #b42318; }
    .pf-btn { height: 40px; padding: 0 1.1rem; border-radius: 10px; background: var(--accent); color: #fff; font-size: 14px; font-weight: 500; transition: background-color .15s; }
    .pf-btn:hover { background: var(--accent-d); } .pf-btn:focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; }
    .pf-link { font-size: 13px; color: var(--ink-2); text-decoration: underline; text-underline-offset: 3px; } .pf-link:hover { color: #b42318; }
    .pf-ok { font-size: 13px; color: #027a48; }
    .pf-hint { margin-top: .35rem; font-size: 12px; color: var(--muted); }
</style>

<div class="pf">
    <h1 class="sr-only">Profil</h1>

    @if($errors->any() && ! $errors->updatePassword->any())
        <div class="mb-5 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">
            <ul class="list-inside list-disc">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    {{-- ===== Kartu profil + foto ===== --}}
    <form method="post" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="pf-card"
          x-data="{ preview: null, removed: false, pick(e) { const f = e.target.files[0]; if (!f) return; this.removed = false; this.preview = URL.createObjectURL(f); } }">
        @csrf @method('patch')

        <div class="pf-cover" aria-hidden="true">
            <svg viewBox="0 0 800 150" preserveAspectRatio="xMidYMid slice" fill="none" stroke="#fff" stroke-opacity=".22" stroke-width="1.2"><circle cx="680" cy="30" r="40"/><circle cx="680" cy="30" r="70"/><circle cx="680" cy="30" r="100"/><circle cx="680" cy="30" r="130"/><circle cx="680" cy="30" r="160"/><circle cx="90" cy="170" r="50"/><circle cx="90" cy="170" r="90"/><circle cx="90" cy="170" r="130"/></svg>
        </div>

        <div class="pf-head">
            <div class="pf-ph">
                <template x-if="preview"><img :src="preview" alt="Pratinjau foto" style="width:96px;height:96px;border-radius:999px;object-fit:cover;box-shadow:0 0 0 4px #fff,0 4px 14px rgb(0 0 0 / .15)"></template>
                <div x-show="!preview"><x-avatar :user="$user" :size="96" :ring="true" /></div>
                <input type="file" id="avatar" name="avatar" accept="image/png,image/jpeg,image/webp" class="sr-only" @change="pick($event)">
                <label for="avatar" title="Ganti foto" aria-label="Ganti foto profil">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 8.5A1.5 1.5 0 0 1 5.5 7H8l1.2-2h5.6L16 7h2.5A1.5 1.5 0 0 1 20 8.5v9a1.5 1.5 0 0 1-1.5 1.5h-13A1.5 1.5 0 0 1 4 17.5v-9z"/><circle cx="12" cy="13" r="3.2"/></svg>
                </label>
            </div>
            <div style="flex:1;min-width:12rem;padding-bottom:.6rem">
                <p class="pf-name">{{ $user->name }}</p>
                <p class="pf-meta"><span class="pf-badge">{{ $roleLabel }}</span><span>{{ $user->email }}</span>@if($since)<span>· Bergabung {{ $since }}</span>@endif</p>
            </div>
            @if($user->avatar)
                <label class="pf-link" style="cursor:pointer;padding-bottom:.4rem"><input type="checkbox" name="remove_avatar" value="1" class="sr-only" x-model="removed" @change="if (removed) { preview = null; document.getElementById('avatar').value = '' }"><span x-text="removed ? 'Foto akan dihapus (batalkan)' : 'Hapus foto'">Hapus foto</span></label>
            @endif
        </div>

        <div class="pf-sec" style="padding-top:0">
            <p class="pf-hint" style="margin:0 0 .5rem">Foto JPG, PNG, atau WebP, maksimal 2 MB. Foto persegi hasilnya paling bagus.</p>
            <div class="grid gap-x-5 sm:grid-cols-2">
                <div class="pf-f"><label for="name">Nama</label><input id="name" name="name" type="text" value="{{ old('name', $user->name) }}" required autocomplete="name"></div>
                <div class="pf-f"><label for="email">Email</label><input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required autocomplete="username"></div>
            </div>
            <div class="mt-5 flex items-center gap-4">
                <button type="submit" class="pf-btn">Simpan perubahan</button>
                @if(session('status') === 'profile-updated')<p class="pf-ok" x-data="{ s: true }" x-show="s" x-init="setTimeout(() => s = false, 2500)">Tersimpan ✓</p>@endif
            </div>
        </div>
    </form>

    <div class="pf-grid">
        {{-- ===== Ganti kata sandi ===== --}}
        <form method="post" action="{{ route('password.update') }}" class="pf-card pf-sec">
            @csrf @method('put')
            <h2>Ganti kata sandi</h2>
            <p>Gunakan kata sandi yang panjang dan unik.</p>
            <div class="pf-f"><label for="cp">Kata sandi saat ini</label><input id="cp" name="current_password" type="password" autocomplete="current-password">@error('current_password', 'updatePassword')<p class="pf-err">{{ $message }}</p>@enderror</div>
            <div class="pf-f"><label for="np">Kata sandi baru</label><input id="np" name="password" type="password" autocomplete="new-password">@error('password', 'updatePassword')<p class="pf-err">{{ $message }}</p>@enderror</div>
            <div class="pf-f"><label for="npc">Ulangi kata sandi baru</label><input id="npc" name="password_confirmation" type="password" autocomplete="new-password"></div>
            <div class="mt-5 flex items-center gap-4">
                <button type="submit" class="pf-btn">Perbarui</button>
                @if(session('status') === 'password-updated')<p class="pf-ok" x-data="{ s: true }" x-show="s" x-init="setTimeout(() => s = false, 2500)">Tersimpan ✓</p>@endif
            </div>
        </form>

        {{-- ===== Hapus akun (fitur bawaan, dipertahankan) ===== --}}
        <div class="pf-card pf-sec">
            @include('profile.partials.delete-user-form')
        </div>
    </div>
</div>
@endsection
