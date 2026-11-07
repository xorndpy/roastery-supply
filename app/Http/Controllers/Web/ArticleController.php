<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Article;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Halaman publik Insight (blog): daftar, pencarian, kategori, dan detail artikel. */
class ArticleController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $categories = Article::categories();
        $category = collect($categories)->first(fn ($c) => Str::slug($c) === $request->query('kategori'));

        $query = Article::published()
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('title', 'like', "%{$search}%")->orWhere('excerpt', 'like', "%{$search}%")->orWhere('body', 'like', "%{$search}%")))
            ->when($category, fn ($q) => $q->where('category', $category));

        $filtering = $search !== '' || $category;

        // Slider: artikel unggulan yang punya foto sampul (hanya di halaman utama Insight, tanpa filter).
        $slides = collect();
        if (!$filtering && (int) $request->query('page', 1) === 1) {
            $slides = Article::published()->where('is_featured', true)->whereNotNull('cover_image')->latest('published_at')->take(5)->get();
        }

        $articles = $query->latest('published_at')->paginate(9)->withQueryString();

        return view('customer.articles.index', compact('articles', 'slides', 'search', 'category', 'categories'));
    }

    public function show(Request $request, string $slug)
    {
        $article = Article::published()->where('slug', $slug)->firstOrFail();

        // Hitung tayangan sekali per sesi per artikel.
        $key = 'viewed_article_' . $article->id;
        if (!$request->session()->has($key)) {
            $article->increment('views');
            $request->session()->put($key, true);
        }

        $related = Article::published()->where('id', '!=', $article->id)
            ->orderByRaw('category = ? desc', [$article->category])->latest('published_at')->take(3)->get();

        return view('customer.articles.show', ['article' => $article, 'related' => $related, 'categories' => Article::categories()]);
    }
}
