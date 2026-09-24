<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'ip_address', 'location', 'monthly_cost', 'provider', 'active'])]
class Server extends Model
{
    public function customerBlogs(): HasMany
    {
        return $this->hasMany(CustomerBlog::class);
    }

    protected function casts(): array
    {
        return ['monthly_cost' => 'decimal:2', 'active' => 'boolean'];
    }
}
