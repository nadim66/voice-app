<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'industry', 'timezone', 'status', 'settings'];

    protected function casts(): array
    {
        return ['settings' => 'array'];
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'memberships')->withPivot('role')->withTimestamps();
    }

    public function knowledgePages(): HasMany
    {
        return $this->hasMany(KnowledgePage::class);
    }

    public function replyCards(): HasMany
    {
        return $this->hasMany(ReplyCard::class);
    }

    public function intentRules(): HasMany
    {
        return $this->hasMany(IntentRule::class);
    }

    public function brainTestCases(): HasMany
    {
        return $this->hasMany(BrainTestCase::class);
    }

    public function brainReleases(): HasMany
    {
        return $this->hasMany(BrainRelease::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function consentEvents(): HasMany
    {
        return $this->hasMany(ConsentEvent::class);
    }

    public function dndEntries(): HasMany
    {
        return $this->hasMany(DndEntry::class);
    }

    public function contactLists(): HasMany
    {
        return $this->hasMany(ContactList::class);
    }

    public function listMembers(): HasMany
    {
        return $this->hasMany(ListMember::class);
    }

    public function phoneNumbers(): HasMany
    {
        return $this->hasMany(PhoneNumber::class);
    }

    public function integrations(): HasMany
    {
        return $this->hasMany(Integration::class);
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    public function callJobs(): HasMany
    {
        return $this->hasMany(CallJob::class);
    }

    public function voiceCalls(): HasMany
    {
        return $this->hasMany(VoiceCall::class);
    }

    public function callTurns(): HasMany
    {
        return $this->hasMany(CallTurn::class);
    }

    public function callEvents(): HasMany
    {
        return $this->hasMany(CallEvent::class);
    }

    public function callSummaries(): HasMany
    {
        return $this->hasMany(CallSummary::class);
    }

    public function followUpTasks(): HasMany
    {
        return $this->hasMany(FollowUpTask::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function outcomes(): HasMany
    {
        return $this->hasMany(Outcome::class);
    }

    public function reviewItems(): HasMany
    {
        return $this->hasMany(ReviewItem::class);
    }

    public function testRuns(): HasMany
    {
        return $this->hasMany(TestRun::class);
    }

    public function usageRecords(): HasMany
    {
        return $this->hasMany(UsageRecord::class);
    }

    public function auditEvents(): HasMany
    {
        return $this->hasMany(AuditEvent::class);
    }
}
