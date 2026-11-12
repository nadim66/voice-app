<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsentEvent extends TenantRecord
{
    protected function casts(): array
    {
        return [
            'granted' => 'boolean',
            'occurred_at' => 'datetime',
        ];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
