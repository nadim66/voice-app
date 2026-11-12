<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FollowUpTask extends TenantRecord
{
    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'is_simulation' => 'boolean',
        ];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
