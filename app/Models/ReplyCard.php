<?php

namespace App\Models;

class ReplyCard extends TenantRecord
{
    protected function casts(): array
    {
        return [
            'fact_codes' => 'array',
            'effective_from' => 'date',
            'expires_at' => 'date',
        ];
    }
}
