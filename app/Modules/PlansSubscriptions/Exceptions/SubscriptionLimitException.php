<?php

namespace App\Modules\PlansSubscriptions\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

class SubscriptionLimitException extends HttpException
{
    public function __construct(string $message)
    {
        parent::__construct(403, $message);
    }
}
