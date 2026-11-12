<?php

namespace App\Models;

class IntentRule extends TenantRecord
{
    protected function casts(): array
    {
        return [
            'examples' => 'array',
            'keywords' => 'array',
            'negatives' => 'array',
        ];
    }
}
