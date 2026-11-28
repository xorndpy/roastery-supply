<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\Cache;

/** sitemap.xml dinamis: halaman utama, kebijakan, kategori, produk aktif, dan artikel terbit. */
class SitemapController extends Controller
{
    private const TTL = 3600; // detik

    public function __invoke()
    {
        $xml = Cache::remember('sitemap.xml', self::TTL, fn () => $this->build());

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    private function build(): string
    {
        $urls = [];
        $add = function (string $loc, $lastmod = null, string $freq = 'weekly', string $prio = '0.5') use (&$urls) {
            $urls[] = compact('loc', 'lastmod', 'freq', 'prio');
        };

        // Halaman utama
        $add(route('home'), null, 'daily', '1.0');
        $add(route('products.index'), null, 'daily', '0.9');
        $add(route('products.brands'), null, 'monthly', '0.5');
        $add(route('articles.index'), null, 'weekly', '0.6');
        $add(route('about'), null, 'monthly', '0.5');
        $add(route('faq'), null, 'monthly', '0.5');
        $add(route('contact.index'), null, 'monthly', '0.5');
        $add(route('branches.index'), null, 'monthly', '0.4');
        $add(route('legal.privacy'), null, 'yearly', '0.2');
        $add(route('legal.terms'), null, 'yearly', '0.2');
        $add(route('legal.returns'), null, 'yearly', '0.2');

        // Kategori yang punya produk aktif
        Category::query()->active()
            ->whereHas('products', fn ($q) => $q->where('is_active', true))
            ->select(['slug', 'updated_at'])
            ->each(fn ($c) => $add(route('products.index', ['category' => $c->slug]), $c->updated_at, 'weekly', '0.7'));

        // Produk aktif
        Product::query()->active()->select(['slug', 'updated_at'])
            ->each(fn ($p) => $add(route('products.show', $p->slug), $p->updated_at, 'weekly', '0.8'));

        // Artikel terbit
        Article::query()->published()->select(['slug', 'updated_at'])
            ->each(fn ($a) => $add(route('articles.show', $a->slug), $a->updated_at, 'monthly', '0.6'));

        $out = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($urls as $u) {
            $out .= '  <url><loc>' . e($u['loc']) . '</loc>';
            if ($u['lastmod']) {
                $out .= '<lastmod>' . $u['lastmod']->toAtomString() . '</lastmod>';
            }
            $out .= '<changefreq>' . $u['freq'] . '</changefreq><priority>' . $u['prio'] . '</priority></url>' . "\n";
        }

        return $out . '</urlset>' . "\n";
    }
}
