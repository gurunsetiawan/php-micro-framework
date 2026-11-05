<?php

declare(strict_types=1);

namespace Micro\Exception;

use Throwable;

class PayloadTooLargeHttpException extends HttpException
{
    /**
     * @param array<string, string|list<string>> $headers
     */
    public function __construct(
        string $message = 'Payload Too Large',
        array $headers = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct(413, $message, $headers, $previous);
    }
}
