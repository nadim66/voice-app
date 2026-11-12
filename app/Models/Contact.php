<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class Contact extends TenantRecord
{
    protected function casts(): array
    {
        return [
            'attributes' => 'array',
            'lead_at' => 'datetime',
        ];
    }

    public function consentEvents(): HasMany
    {
        return $this->hasMany(ConsentEvent::class);
    }

    public function outcomes(): HasMany
    {
        return $this->hasMany(Outcome::class);
    }
}
