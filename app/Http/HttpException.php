<?php

declare(strict_types=1);

namespace App\Http;

/** Error HTTP yang pesannya aman ditampilkan (404, 403, 405, 419). */
final class HttpException extends \RuntimeException
{
    public function __construct(public readonly int $status, string $message)
    {
        parent::__construct($message, $status);
    }
}
