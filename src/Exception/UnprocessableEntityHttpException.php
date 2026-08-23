<?php

declare(strict_types=1);

namespace Micro\Exception;

use Throwable;

class UnprocessableEntityHttpException extends HttpException
{
    /**
     * @param array<string, string|list<string>> $headers
     */
    public function __construct(
        string $message = 'Unprocessable Entity',
        array $headers = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct(422, $message, $headers, $previous);
    }
}
