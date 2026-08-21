<?php

declare(strict_types=1);

namespace Micro\Exception;

use Throwable;

final class UnauthorizedHttpException extends HttpException
{
    /**
     * @param array<string, string|list<string>> $headers
     */
    public function __construct(
        string $message = 'Unauthorized',
        array $headers = [],
        ?Throwable $previous = null,
    ) {
        $headers = array_merge(['WWW-Authenticate' => 'Bearer'], $headers);
        parent::__construct(401, $message, $headers, $previous);
    }
}
