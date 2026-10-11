<?php

namespace App\Exceptions\Domain;

final class CheckInAlreadyClosed extends DomainException
{
    public function __construct()
    {
        parent::__construct(409, 'already_checked_out', 'This check-in has already been checked out.');
    }
}
