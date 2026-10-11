<?php

namespace App\Exceptions\Domain;

final class TenantHeaderInvalid extends DomainException
{
    public function __construct()
    {
        parent::__construct(400, 'tenant_header_invalid', 'The X-Tenant-ID header is missing or is not a valid UUID.');
    }
}
