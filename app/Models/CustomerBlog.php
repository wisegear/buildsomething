<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['server_id', 'user_id', 'subdomain', 'description', 'terms_accepted', 'status', 'wp_admin_username', 'wp_admin_password', 'failure_reason', 'provisioned_at', 'pending_wp_admin_password', 'password_reset_token'])]
#[Hidden(['wp_admin_password', 'pending_wp_admin_password', 'password_reset_token'])]
class CustomerBlog extends Model
{
    protected function casts(): array
    {
        return [
            'wp_admin_username' => 'encrypted',
            'wp_admin_password' => 'encrypted',
            'pending_wp_admin_password' => 'encrypted',
            'provisioned_at' => 'datetime',
            'terms_accepted' => 'boolean',
        ];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getDomainAttribute(): string
    {
        return $this->subdomain.'.blogshed.uk';
    }
}
