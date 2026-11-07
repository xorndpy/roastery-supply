@extends('layouts.admin')

@section('title', $article->exists ? 'Edit Artikel' : 'Tulis Artikel')

@section('content')
@php
    $f = fn ($k, $d = null) => old($k, $article->{$k} ?? $d);
    $publishedAt = old('published_at', $article->published_at?->copy()->timezone('Asia/Jakarta')->format('Y-m-d\TH:i'));
@endphp
<div class="dash">
    <a href="{{ route('admin.articles.index') }}" class="text-sm text-stone-500 hover:text-stone-800">← Kembali ke Artikel</a>
    <header class="dash-head" style="margin-top:.75rem"><div><h1 class="dash-h1" style="margin-top:0">{{ $article->exists ? 'Edit artikel' : 'Tulis artikel' }}</h1></div></header>

    @if($errors->any())
        <div class="mb-5 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert"><ul class="list-disc pl-5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <form method="POST" enctype="multipart/form-data" action="{{ $article->exists ? route('admin.articles.update', $article->id) : route('admin.articles.store') }}" class="grid gap-5 lg:grid-cols-3"
          x-data="{ preview: null, busy: false, async upload(e) {
              const file = e.target.files[0]; if (!file) return; this.busy = true;
              const fd = new FormData(); fd.append('image', file);
              try {
                  const r = await fetch('{{ route('admin.articles.upload-image') }}', { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, Accept: 'application/json' }, body: fd });
                  const j = await r.json(); if (!r.ok) throw new Error(j.message || 'Gagal mengunggah foto.');
                  const t = this.$refs.body, pos = t.selectionStart ?? t.value.length;
                  t.value = t.value.slice(0, pos) + '\n\n![Deskripsi foto](' + j.url + ')\n\n' + t.value.slice(pos);
              } catch (err) { alert(err.message); } finally { this.busy = false; e.target.value = ''; }
          } }">
        @csrf
        @if($article->exists) @method('PUT') @endif

        <div class="space-y-5 lg:col-span-2">
            <section class="dash-card space-y-4">
                <label class="block text-sm font-medium text-stone-700">Judul
                    <input type="text" name="title" value="{{ $f('title') }}" required maxlength="160" class="mt-1 block w-full rounded-lg border border-stone-300 px-3 py-2.5 text-base">
                </label>
                <label class="block text-sm font-medium text-stone-700">Ringkasan <span class="font-normal text-stone-500">(tampil di kartu dan hasil pencarian, maks. 300 karakter)</span>
                    <textarea name="excerpt" rows="2" maxlength="300" class="mt-1 block w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">{{ $f('excerpt') }}</textarea>
                </label>
                <div>
                    <div class="mb-1 flex items-center justify-between">
                        <label for="body" class="text-sm font-medium text-stone-700">Isi artikel</label>
                        <label class="pay-btn is-quiet cursor-pointer !py-1.5" :class="busy && 'opacity-60'">
                            <span x-text="busy ? 'Mengunggah…' : '+ Sisipkan foto'">+ Sisipkan foto</span>
                            <input type="file" accept="image/png,image/jpeg,image/webp" class="sr-only" @change="upload($event)" :disabled="busy">
                        </label>
                    </div>
                    <textarea id="body" x-ref="body" name="body" rows="18" required class="block w-full rounded-lg border border-stone-300 px-3 py-2.5 font-mono text-[13.5px] leading-relaxed">{{ $f('body') }}</textarea>
                    <details class="mt-2 text-xs text-stone-600">
                        <summary class="cursor-pointer font-medium">Panduan penulisan (Markdown)</summary>
                        <pre class="mt-2 overflow-x-auto rounded-md bg-stone-100 p-3 leading-relaxed"><code>## Judul bagian
### Sub-judul
Teks biasa dengan **tebal**, *miring*, dan [tautan](https://contoh.com).

- Poin pertama
- Poin kedua

1. Langkah satu
2. Langkah dua

&gt; Kutipan penting

![Deskripsi foto](alamat-foto)   atau pakai tombol "Sisipkan foto"</code></pre>
                    </details>
                </div>
            </section>
        </div>

        <div class="space-y-5">
            <section class="dash-card space-y-4">
                <div class="dash-card-head" style="margin-bottom:0"><h2>Penerbitan</h2></div>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_published" value="1" @checked($f('is_published')) class="rounded border-stone-400"> Terbitkan</label>
                <label class="block text-sm text-stone-700">Tanggal tayang <span class="text-xs text-stone-500">(WIB; kosong = sekarang; tanggal depan = terjadwal)</span>
                    <input type="datetime-local" name="published_at" value="{{ $publishedAt }}" class="mt-1 block w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
                </label>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_featured" value="1" @checked($f('is_featured')) class="rounded border-stone-400"> Tampilkan di slider Insight <span class="text-xs text-stone-500">(wajib punya foto sampul)</span></label>
                <button class="pay-btn is-primary w-full !py-2.5">Simpan</button>
            </section>

            <section class="dash-card space-y-4">
                <div class="dash-card-head" style="margin-bottom:0"><h2>Detail</h2></div>
                <label class="block text-sm text-stone-700">Kategori
                    <select name="category" required class="mt-1 block w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
                        @foreach($categories as $c)<option value="{{ $c }}" @selected($f('category') === $c)>{{ $c }}</option>@endforeach
                    </select>
                </label>
                <label class="block text-sm text-stone-700">Penulis
                    <input type="text" name="author_name" value="{{ $f('author_name') }}" maxlength="80" class="mt-1 block w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
                </label>
                <label class="block text-sm text-stone-700">Video <span class="text-xs text-stone-500">(tautan YouTube atau Vimeo, opsional)</span>
                    <input type="url" name="video_url" value="{{ $f('video_url') }}" placeholder="https://www.youtube.com/watch?v=…" class="mt-1 block w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
                </label>
            </section>

            <section class="dash-card space-y-3">
                <div class="dash-card-head" style="margin-bottom:0"><h2>Foto sampul</h2></div>
                @if($article->cover_url)
                    <img src="{{ $article->cover_url }}" alt="Sampul saat ini" class="aspect-[16/9] w-full rounded-lg object-cover" x-show="!preview">
                    <label class="flex items-center gap-2 text-sm text-stone-600"><input type="checkbox" name="remove_cover" value="1" class="rounded border-stone-400"> Hapus sampul</label>
                @endif
                <img x-show="preview" x-cloak :src="preview" alt="Pratinjau sampul" class="aspect-[16/9] w-full rounded-lg object-cover">
                <input type="file" name="cover" accept="image/png,image/jpeg,image/webp" class="block w-full text-sm" @change="preview = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null">
                <p class="text-xs text-stone-500">Saran 1600 × 900 px (rasio 16:9), JPG/WEBP, maks. 3 MB.</p>
            </section>
        </div>
    </form>
</div>
@endsection
