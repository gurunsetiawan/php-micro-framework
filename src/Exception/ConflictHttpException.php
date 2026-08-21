<?php

declare(strict_types=1);

namespace Micro\Exception;

use Throwable;

final class ConflictHttpException extends HttpException
{
    /**
     * @param array<string, string|list<string>> $headers
     */
    public function __construct(
        string $message = 'Conflict',
        array $headers = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct(409, $message, $headers, $previous);
    }
}
