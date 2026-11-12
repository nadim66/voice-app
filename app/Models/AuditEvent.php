<?php

namespace App\Models;

class AuditEvent extends TenantRecord
{
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }
}
