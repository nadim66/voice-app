<?php

namespace App\Models;

class BrainTestCase extends TenantRecord
{
    protected function casts(): array
    {
        return [
            'turns' => 'array',
            'expected_end' => 'boolean',
        ];
    }
}
