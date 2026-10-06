<?php

namespace App\Exceptions;

use Exception;

class ThesisNotEligibleForArchivingException extends Exception
{
    public function __construct(protected array $reasons)
    {
        parent::__construct('This thesis is not eligible for archiving.');
        $this->code = 422;
    }

    public function getReasons(): array
    {
        return $this->reasons;
    }
}