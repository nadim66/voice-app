<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends TenantRecord
{
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
