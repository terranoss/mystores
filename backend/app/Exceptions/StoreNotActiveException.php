<?php

namespace App\Exceptions;

use Exception;

class StoreNotActiveException extends Exception
{
    public function __construct(string $message = "Store token missing or inactive.", int $code = 400)
    {
        parent::__construct($message, $code);
    }
}
