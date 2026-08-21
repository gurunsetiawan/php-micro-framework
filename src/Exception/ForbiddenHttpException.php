<?php

declare(strict_types=1);

namespace Micro\Exception;

use Throwable;

final class ForbiddenHttpException extends HttpException
{
    /**
     * @param array<string, string|list<string>> $headers
     */
    public function __construct(
        string $message = 'Forbidden',
        array $headers = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct(403, $message, $headers, $previous);
    }
}
