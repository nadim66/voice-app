<?php

namespace App\Filament\Resources;

use App\Support\TenantAccess;
use App\Support\TenantContext;
use Filament\Resources\Resource;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

abstract class WorkspaceResource extends Resource
{
    public static function getAuthorizationResponse(string|\UnitEnum $action, ?Model $record = null): Response
    {
        $action = $action instanceof \BackedEnum ? $action->value : ($action instanceof \UnitEnum ? $action->name : $action);
        $ownsRecord = $record !== null && (int) $record->tenant_id === app(TenantContext::class)->id();
        $allowed = match ($action) {
            'viewAny' => static::canViewAny(),
            'view' => $ownsRecord && static::canViewAny(),
            'create' => static::canCreate(),
            'update' => $ownsRecord && static::canEdit($record),
            default => false,
        };

        return $allowed ? Response::allow() : Response::deny();
    }

    protected static string $workspaceAbility = 'operate';

    protected static string $viewAbility = 'view';

    protected static bool $recordsReadOnly = false;

    protected static bool $recordsEditable = true;

    public static function canViewAny(): bool
    {
        return TenantAccess::allows(static::$viewAbility);
    }

    public static function canCreate(): bool
    {
        return ! static::$recordsReadOnly && TenantAccess::allows(static::$workspaceAbility);
    }

    public static function canEdit(Model $record): bool
    {
        return static::$recordsEditable && (int) $record->tenant_id === app(TenantContext::class)->id() && static::canCreate();
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }
}
