<?php

namespace App\Support;

use App\Models\Tenant;
use LogicException;

/**
 * Request-scoped holder for the tenant resolved from X-Tenant-ID.
 */
final class TenantContext
{
    private ?Tenant $tenant = null;

    public function set(Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function get(): Tenant
    {
        return $this->tenant ?? throw new LogicException('No tenant in context.');
    }

    public function id(): string
    {
        return $this->get()->id;
    }
}
