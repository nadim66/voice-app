<?php

namespace App\Models;

class UsageRecord extends TenantRecord
{
    protected function casts(): array
    {
        return [
            'cost_tk' => 'decimal:4',
        ];
    }
}
