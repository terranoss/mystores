<?php

namespace App\Exceptions;

use Exception;

class ProductAuthorizationException extends Exception
{
    public function __construct(string $message = "Forbidden. Product does not belong to the user's stores.", int $code = 403)
    {
        parent::__construct($message, $code);
    }
}
