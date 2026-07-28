<?php

namespace App\Exceptions;

use Exception;

class SchedulingConflictException extends Exception
{
    public function __construct(string $message)
    {
        parent::__construct($message);
        $this->code = 422;
    }
}