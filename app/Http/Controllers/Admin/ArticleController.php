<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ArticleController extends Controller
{
    public function index(Request $request)
    {
        $query = Article::query()->latest('id');

        if ($s = trim((string) $request->search)) {
            $query->where('title', 'like', "%{$s}%");
        }
        match ($request->status) {
            'terbit'    => $query->published(),
            'draft'     => $query->where('is_published', false),
            'terjadwal' => $query->where('is_published', true)->where('published_at', '>', now()),
            default     => null,
        };

        $articles = $query->paginate(12)->withQueryString();
        $counts = [
            'semua' => Article::count(),
            'terbit' => Article::published()->count(),
            'draft' => Article::where('is_published', false)->count(),
        ];

        return view('admin.articles.index', compact('articles', 'counts'));
    }

    public function create()
    {
        return view('admin.articles.form', ['article' => new Article(['author_name' => auth()->user()->name]), 'categories' => Article::categories()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['slug'] = Article::uniqueSlug($data['title']);
        $data['cover_image'] = $request->file('cover')?->store('articles', 'public');

        $article = Article::create($data);
        activity('artikel')->performedOn($article)->causedBy(auth()->user())->log('Artikel dibuat: ' . $article->title);

        return redirect()->route('admin.articles.index')->with('success', 'Artikel disimpan.');
    }

    public function edit(Article $article)
    {
        return view('admin.articles.form', ['article' => $article, 'categories' => Article::categories()]);
    }

    public function update(Request $request, Article $article)
    {
        $data = $this->validated($request, $article);

        if ($request->boolean('remove_cover') && $article->cover_image) {
            Storage::disk('public')->delete($article->cover_image);
            $data['cover_image'] = null;
        }
        if ($file = $request->file('cover')) {
            if ($article->cover_image) {
                Storage::disk('public')->delete($article->cover_image);
            }
            $data['cover_image'] = $file->store('articles', 'public');
        }
        // Tautan (slug) dibiarkan tetap agar alamat artikel yang sudah dibagikan tidak berubah.

        $article->update($data);
        activity('artikel')->performedOn($article)->causedBy(auth()->user())->log('Artikel diubah: ' . $article->title);

        return redirect()->route('admin.articles.index')->with('success', 'Artikel diperbarui.');
    }

    public function destroy(Article $article)
    {
        $article->delete();
        activity('artikel')->performedOn($article)->causedBy(auth()->user())->log('Artikel dihapus: ' . $article->title);

        return back()->with('success', 'Artikel dihapus.');
    }

    public function togglePublish(Article $article)
    {
        $publish = !$article->is_published;
        $article->update(['is_published' => $publish, 'published_at' => $publish ? ($article->published_at ?? now()) : $article->published_at]);

        return back()->with('success', $publish ? 'Artikel diterbitkan.' : 'Artikel dijadikan draft.');
    }

    /** Unggah foto untuk disisipkan ke isi artikel; mengembalikan URL-nya. */
    public function uploadImage(Request $request)
    {
        $request->validate(['image' => 'required|image|mimes:jpg,jpeg,png,webp|max:3072']);

        return response()->json(['url' => media_url($request->file('image')->store('articles/isi', 'public'))]);
    }

    private function validated(Request $request, ?Article $article = null): array
    {
        $data = $request->validate([
            'title'        => 'required|string|max:160',
            'category'     => ['required', Rule::in(Article::categories())],
            'excerpt'      => 'nullable|string|max:300',
            'body'         => 'required|string|max:60000',
            'cover'        => 'nullable|image|mimes:jpg,jpeg,png,webp|max:3072',
            'video_url'    => ['nullable', 'url', 'max:300', function ($attr, $value, $fail) {
                if ($value && !preg_match('~^https?://([a-z0-9-]+\.)?(youtube\.com|youtu\.be|youtube-nocookie\.com|vimeo\.com)/~i', $value)) {
                    $fail('Video harus berupa tautan YouTube atau Vimeo.');
                }
            }],
            'author_name'  => 'nullable|string|max:80',
            'published_at' => 'nullable|date',
        ], [
            'title.required' => 'Judul wajib diisi.', 'body.required' => 'Isi artikel wajib diisi.',
            'category.in' => 'Pilih kategori dari daftar.', 'cover.image' => 'Sampul harus berupa gambar.', 'cover.max' => 'Ukuran sampul maksimal 3 MB.',
        ]);

        $data['is_published'] = $request->boolean('is_published');
        $data['is_featured'] = $request->boolean('is_featured');
        unset($data['cover']);

        $hasCover = $request->hasFile('cover') || ($article?->cover_image && !$request->boolean('remove_cover'));
        if ($data['is_featured'] && !$hasCover) {
            throw \Illuminate\Validation\ValidationException::withMessages(['is_featured' => 'Artikel yang disematkan di slider wajib punya foto sampul.']);
        }

        // Terbit tanpa tanggal = terbit sekarang. Tanggal masa depan = terjadwal.
        $data['published_at'] = $data['published_at'] ?? null;
        if ($data['is_published'] && !$data['published_at']) {
            $data['published_at'] = $article?->published_at ?? now();
        }

        return $data;
    }
}
