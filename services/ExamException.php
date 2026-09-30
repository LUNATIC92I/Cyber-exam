<?php
declare(strict_types=1);

namespace App\Services;

/** Business-rule violation in the exam flow; the code is used as HTTP status. */
final class ExamException extends \RuntimeException
{
    public function __construct(string $message, int $code = 403)
    {
        parent::__construct($message, $code);
    }
}
