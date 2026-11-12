<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VoiceCall extends TenantRecord
{
    protected function casts(): array
    {
        return [
            'context' => 'array',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'is_simulation' => 'boolean',
            'cost_tk' => 'decimal:4',
        ];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function turns(): HasMany
    {
        return $this->hasMany(CallTurn::class);
    }

    public function brainRelease(): BelongsTo
    {
        return $this->belongsTo(BrainRelease::class);
    }
}
