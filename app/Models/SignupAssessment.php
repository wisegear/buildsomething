<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SignupAssessment extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'registered_at' => 'immutable_datetime',
            'previous_ip_registrations' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
