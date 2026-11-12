<?php

namespace App\Models;

class TestRun extends TenantRecord
{
    protected function casts(): array
    {
        return [
            'failed_cases' => 'array',
        ];
    }
}
