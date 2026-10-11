<?php

namespace App\Exceptions\Domain;

final class TenantNotFound extends DomainException
{
    public function __construct()
    {
        parent::__construct(404, 'tenant_not_found', 'Tenant not found.');
    }
}
