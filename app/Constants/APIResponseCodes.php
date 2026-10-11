<?php

namespace App\Constants;

/**
 * Stable response codes. Title, message, HTTP status and icon for each code
 * live in config/api-response-codes.php.
 */
class APIResponseCodes
{
    // Success Codes
    const SUCCESS = 'S200';

    const CREATED = 'S201';

    // General Error Codes
    const BAD_REQUEST = 'ERR_400';

    const NOT_FOUND = 'ERR_404';

    const CONFLICT = 'ERR_409';

    const INTERNAL_SERVER_ERROR = 'ERR_500';

    // API Error Codes
    const VALIDATION_FAILED = 'E1000';
}
