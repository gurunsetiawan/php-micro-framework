<?php

declare(strict_types=1);

namespace Micro\Exception;

use Throwable;

final class BadRequestHttpException extends HttpException
{
    /**
     * @param array<string, string|list<string>> $headers
     */
    public function __construct(
        string $message = 'Bad Request',
        array $headers = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct(400, $message, $headers, $previous);
    }
}
