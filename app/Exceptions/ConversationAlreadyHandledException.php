<?php

namespace App\Exceptions;

use DomainException;

class ConversationAlreadyHandledException extends DomainException
{
    protected $message = 'This conversation is already being handled.';
}
