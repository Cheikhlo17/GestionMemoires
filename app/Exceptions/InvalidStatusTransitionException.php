<?php

namespace App\Exceptions;

use Exception;

class InvalidStatusTransitionException extends Exception
{
    public function __construct(string $from, string $to)
    {
        parent::__construct("Cannot transition thesis status from '{$from}' to '{$to}'.");
        $this->code = 422;
    }
}