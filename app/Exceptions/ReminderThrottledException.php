<?php

namespace App\Exceptions;

use DomainException;

class ReminderThrottledException extends DomainException
{
    protected $message = "We've already reminded our support team about this request. We're still reviewing it.";
}
