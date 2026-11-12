<?php

namespace App\Models;

use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

abstract class TenantRecord extends Model
{
    use HasFactory;

    protected $guarded = ['id', 'tenant_id'];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    protected static function booted(): void
    {
        static::addGlobalScope('workspace', function (Builder $query): void {
            $id = app(TenantContext::class)->id();
            $id === null ? $query->whereRaw('1 = 0') : $query->where($query->getModel()->qualifyColumn('tenant_id'), $id);
        });
        static::saving(function (self $record): void {
            $id = app(TenantContext::class)->requireTenant()->id;
            if ($record->exists && ($record instanceof ConsentEvent || $record instanceof AuditEvent || $record instanceof BrainRelease || $record instanceof DndEntry)) {
                throw new LogicException('Ledger records cannot be edited. Add a new event instead.');
            }
            if ($record->tenant_id !== null && (int) $record->tenant_id !== $id) {
                throw new LogicException('Cross-client writes are not allowed.');
            }
            $record->tenant_id = $id;
            foreach ([
                'contact_id' => Contact::class, 'contact_list_id' => ContactList::class,
                'campaign_id' => Campaign::class, 'brain_release_id' => BrainRelease::class,
                'voice_call_id' => VoiceCall::class,
            ] as $key => $model) {
                if ($record->isDirty($key) && $record->getAttribute($key) !== null && ! $model::whereKey($record->getAttribute($key))->exists()) {
                    throw new LogicException('A related record belongs to another client.');
                }
            }
        });
        static::deleting(function (self $record): void {
            if ($record instanceof ConsentEvent || $record instanceof AuditEvent || $record instanceof BrainRelease || $record instanceof DndEntry) {
                throw new LogicException('Published releases and ledger records cannot be deleted.');
            }
            if ((int) $record->tenant_id !== app(TenantContext::class)->id()) {
                throw new LogicException('Cross-client deletion is not allowed.');
            }
        });
        foreach (['created', 'updated', 'deleted'] as $event) {
            static::$event(function (self $record) use ($event): void {
                if ($record instanceof AuditEvent) {
                    return;
                }
                AuditEvent::create([
                    'user_id' => auth()->id(),
                    'action' => class_basename($record).'.'.$event,
                    'target' => (string) $record->getKey(),
                    'metadata' => ['fields' => array_keys($record->getChanges())],
                ]);
            });
        }
    }
}
