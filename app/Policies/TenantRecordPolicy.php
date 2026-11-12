<?php

namespace App\Policies;

use App\Models\AuditEvent;
use App\Models\BrainRelease;
use App\Models\CallJob;
use App\Models\CallTurn;
use App\Models\ConsentEvent;
use App\Models\TenantRecord;
use App\Models\TestRun;
use App\Models\UsageRecord;
use App\Models\User;
use App\Models\VoiceCall;
use App\Support\TenantAccess;
use App\Support\TenantContext;

class TenantRecordPolicy
{
    public function viewAny(User $user): bool
    {
        return TenantAccess::allows('view');
    }

    public function view(User $user, TenantRecord $record): bool
    {
        return (int) $record->tenant_id === app(TenantContext::class)->id() && $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return TenantAccess::allows('operate');
    }

    public function update(User $user, TenantRecord $record): bool
    {
        return $this->view($user, $record) && ! in_array($record::class, [AuditEvent::class, ConsentEvent::class, BrainRelease::class, TestRun::class, UsageRecord::class, VoiceCall::class, CallTurn::class, CallJob::class]) && TenantAccess::allows('operate');
    }

    public function delete(User $user, TenantRecord $record): bool
    {
        return false;
    }
}
