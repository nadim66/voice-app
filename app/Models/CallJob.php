<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CallJob extends TenantRecord
{
    protected function casts(): array
    {
        return [
            'next_attempt_at' => 'datetime',
        ];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }
}
