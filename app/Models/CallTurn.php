<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CallTurn extends TenantRecord
{
    protected function casts(): array
    {
        return [
            'evidence' => 'array',
            'confidence' => 'float',
        ];
    }

    public function voiceCall(): BelongsTo
    {
        return $this->belongsTo(VoiceCall::class);
    }
}
