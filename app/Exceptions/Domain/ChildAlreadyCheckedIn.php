<?php

namespace App\Exceptions\Domain;

final class ChildAlreadyCheckedIn extends DomainException
{
    public function __construct()
    {
        parent::__construct(409, 'child_already_checked_in', 'Child is already checked in to this room.');
    }
}
