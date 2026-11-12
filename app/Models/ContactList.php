<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class ContactList extends TenantRecord
{
    public function members(): HasMany
    {
        return $this->hasMany(ListMember::class);
    }
}
