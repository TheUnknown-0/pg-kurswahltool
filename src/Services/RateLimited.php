<?php

declare(strict_types=1);

namespace App\Services;

/** GitHub hat die Abfrage wegen zu vieler Anfragen abgelehnt. */
final class RateLimited extends \RuntimeException
{
    public function __construct(public readonly int $retryAfter)
    {
        parent::__construct('Rate limit');
    }
}
