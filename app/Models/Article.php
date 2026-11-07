<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Article extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'title', 'slug', 'category', 'excerpt', 'body', 'cover_image', 'video_url', 'author_name',
        'is_published', 'is_featured', 'published_at', 'views',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'is_featured'  => 'boolean',
        'published_at' => 'datetime',
    ];

    /** Sudah terbit dan waktunya sudah tiba (mendukung jadwal tayang). */
    public function scopePublished($q)
    {
        return $q->where('is_published', true)->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    public static function categories(): array
    {
        return (array) config('blog.categories', []);
    }

    public static function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'artikel';
        $slug = $base;
        $i = 2;
        while (static::withTrashed()->where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }

    public function getCoverUrlAttribute(): ?string
    {
        return $this->cover_image ? asset('storage/' . $this->cover_image) : null;
    }

    /** Isi (Markdown) menjadi HTML. Tag HTML mentah dibuang dan tautan berbahaya ditolak. */
    public function getHtmlAttribute(): string
    {
        return (string) Str::markdown((string) $this->body, ['html_input' => 'strip', 'allow_unsafe_links' => false]);
    }

    public function getReadingMinutesAttribute(): int
    {
        return max(1, (int) ceil(str_word_count(strip_tags((string) $this->body)) / 200));
    }

    public function getExcerptTextAttribute(): string
    {
        return $this->excerpt ?: Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags($this->html))), 140);
    }

    /** @return array{provider: string, src: string}|null */
    public function getVideoEmbedAttribute(): ?array
    {
        $u = trim((string) $this->video_url);
        if ($u === '') {
            return null;
        }
        if (preg_match('~(?:youtu\.be/|youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/))([A-Za-z0-9_-]{6,15})~', $u, $m)) {
            return ['provider' => 'youtube', 'src' => 'https://www.youtube-nocookie.com/embed/' . $m[1]];
        }
        if (preg_match('~vimeo\.com/(?:video/)?(\d{5,12})~', $u, $m)) {
            return ['provider' => 'vimeo', 'src' => 'https://player.vimeo.com/video/' . $m[1]];
        }

        return null;
    }
}
