<?php

declare(strict_types=1);

namespace Micro\Exception;

use Throwable;

final class NotFoundHttpException extends HttpException
{
    /**
     * @param array<string, string|list<string>> $headers
     */
    public function __construct(
        string $message = 'Not Found',
        array $headers = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct(404, $message, $headers, $previous);
    }
}
