<?php

namespace App\Models;

class CallEvent extends TenantRecord
{
    protected function casts(): array
    {
        return [
            'data' => 'array',
        ];
    }
}
