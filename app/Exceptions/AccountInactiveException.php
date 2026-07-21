<?php

namespace App\Exceptions;

use Exception;

class AccountInactiveException extends Exception
{
    protected $message = 'This account has been deactivated. Please contact the administrator.';
    protected $code = 403;
}