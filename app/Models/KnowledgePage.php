<?php

namespace App\Models;

class KnowledgePage extends TenantRecord
{
    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'expires_at' => 'date',
        ];
    }
}
