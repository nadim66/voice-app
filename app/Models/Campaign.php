<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends TenantRecord
{
    protected $attributes = ['goal' => 'registration', 'mode' => 'hybrid', 'purpose' => 'service', 'status' => 'draft', 'max_attempts' => 3, 'retry_gap_minutes' => 240, 'daily_cap' => 300, 'concurrency' => 5, 'budget_tk' => 0];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'budget_tk' => 'decimal:2',
        ];
    }

    public function contactList(): BelongsTo
    {
        return $this->belongsTo(ContactList::class);
    }

    public function brainRelease(): BelongsTo
    {
        return $this->belongsTo(BrainRelease::class);
    }

    public function callJobs(): HasMany
    {
        return $this->hasMany(CallJob::class);
    }
}
