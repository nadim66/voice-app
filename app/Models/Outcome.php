<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Outcome extends TenantRecord
{
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
        ];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
