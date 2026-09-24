<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

#[Fillable(['title', 'slug', 'seo_summary', 'body', 'image', 'image_alt', 'is_published', 'post_date'])]
class Post extends Model
{
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class)->orderBy('name');
    }

    protected function casts(): array
    {
        return ['is_published' => 'boolean', 'post_date' => 'date'];
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true)->whereDate('post_date', '<=', today());
    }

    public static function uniqueSlug(string $title): string
    {
        $base = Str::substr(Str::slug($title) ?: 'post', 0, 240);
        $slug = $base;
        $n = 2;
        while (static::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$n++;
        }

        return $slug;
    }
}
