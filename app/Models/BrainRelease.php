<?php

namespace App\Models;

class BrainRelease extends TenantRecord
{
    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
            'test_results' => 'array',
            'published_at' => 'datetime',
        ];
    }
}
