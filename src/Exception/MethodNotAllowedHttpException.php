<?php

declare(strict_types=1);

namespace Micro\Exception;

use Throwable;

final class MethodNotAllowedHttpException extends HttpException
{
    /**
     * @param list<string> $allowedMethods
     * @param array<string, string|list<string>> $headers
     */
    public function __construct(
        private readonly array $allowedMethods = [],
        string $message = 'Method Not Allowed',
        array $headers = [],
        ?Throwable $previous = null,
    ) {
        if ($this->allowedMethods !== []) {
            $headers['Allow'] = implode(', ', $this->allowedMethods);
        }

        parent::__construct(405, $message, $headers, $previous);
    }

    /**
     * @return list<string>
     */
    public function getAllowedMethods(): array
    {
        return $this->allowedMethods;
    }
}
