<?php
declare(strict_types=1);

namespace App\Core;

/** Used by the test-suite to capture redirects / JSON / aborts instead of exiting. */
final class HttpResponseException extends \RuntimeException
{
    public function __construct(public readonly array $payload)
    {
        parent::__construct('HTTP response: ' . json_encode($payload));
    }
}
