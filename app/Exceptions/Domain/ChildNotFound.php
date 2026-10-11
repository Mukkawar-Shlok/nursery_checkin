<?php

namespace App\Exceptions\Domain;

final class ChildNotFound extends DomainException
{
    public function __construct()
    {
        parent::__construct(404, 'child_not_found', 'Child record not found.');
    }
}
