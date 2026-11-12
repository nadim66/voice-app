<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ListMember extends TenantRecord
{
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function contactList(): BelongsTo
    {
        return $this->belongsTo(ContactList::class);
    }
}
