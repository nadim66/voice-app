<?php

namespace App\Models;

class Integration extends TenantRecord
{
    protected function casts(): array
    {
        return [
            'config' => 'array',
            'last_sync_at' => 'datetime',
        ];
    }
}
