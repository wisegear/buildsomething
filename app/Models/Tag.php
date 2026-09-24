<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name'])]
class Tag extends Model
{
    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class);
    }

    public static function names(string $input): array
    {
        return collect(explode(',', $input))
            ->map(fn ($name) => mb_strtolower(trim(preg_replace('/\s+/u', ' ', $name))))
            ->filter(fn ($name) => $name !== '')->unique()->values()->all();
    }
}
