<?php

namespace App\Support;

use App\Models\Tenant;
use Closure;
use LogicException;

class TenantContext
{
    private ?Tenant $tenant = null;

    public function tenant(): ?Tenant
    {
        return $this->tenant;
    }

    public function id(): ?int
    {
        return $this->tenant?->id;
    }

    public function requireTenant(): Tenant
    {
        return $this->tenant ?? throw new LogicException('An authorized client workspace is required.');
    }

    public function run(Tenant $tenant, Closure $callback): mixed
    {
        $previous = $this->tenant;
        $this->tenant = $tenant;
        try {
            return $callback();
        } finally {
            $this->tenant = $previous;
        }
    }
}
