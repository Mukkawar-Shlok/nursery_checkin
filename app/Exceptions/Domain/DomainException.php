<?php

namespace App\Exceptions\Domain;

use RuntimeException;

/**
 * Base for business-rule errors. Carries the HTTP status and a stable code,
 * so the handler in bootstrap/app.php is one mapping and tests assert on code.
 */
abstract class DomainException extends RuntimeException
{
    public function __construct(
        public readonly int $status,
        public readonly string $errorCode,
        string $message,
        public readonly array $extra = [],
    ) {
        parent::__construct($message);
    }
}
