<?php

declare(strict_types=1);

namespace Micro\Middleware;

use JsonException;
use Micro\Exception\BadRequestHttpException;
use Micro\Exception\FrameworkException;
use Micro\Exception\PayloadTooLargeHttpException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class JsonBodyParserMiddleware implements MiddlewareInterface
{
    /**
     * Default maximum request body size (5 megabytes).
     */
    public const int DEFAULT_MAX_BODY_SIZE = 5 * 1024 * 1024;

    /**
     * @param int $maxBodySizeBytes Maximum allowed payload size in bytes (default 5MB).
     * @throws FrameworkException If maxBodySizeBytes is less than or equal to zero.
     */
    public function __construct(
        private int $maxBodySizeBytes = self::DEFAULT_MAX_BODY_SIZE,
    ) {
        if ($this->maxBodySizeBytes <= 0) {
            throw new FrameworkException('maxBodySizeBytes must be greater than zero.');
        }
    }

    /**
     * Process an incoming server request and parse JSON body if applicable.
     *
     * @throws PayloadTooLargeHttpException If request payload exceeds the allowed limit.
     * @throws BadRequestHttpException If the JSON payload is malformed or not an object/array.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $contentType = $request->getHeaderLine('Content-Type');

        if ($this->isJsonContentType($contentType)) {
            // 1. Fast-path check: Content-Length header with request smuggling guards
            $contentLengthHeader = trim($request->getHeaderLine('Content-Length'));
            if ($contentLengthHeader !== '') {
                if (str_contains($contentLengthHeader, ',')) {
                    // Validate that all comma-separated values are identical (RFC 9110)
                    $lengths = array_map('trim', explode(',', $contentLengthHeader));
                    if (count(array_unique($lengths)) !== 1 || !ctype_digit($lengths[0])) {
                        throw new BadRequestHttpException('Conflicting or invalid Content-Length headers.');
                    }
                    $contentLength = (int) $lengths[0];
                } elseif (!ctype_digit($contentLengthHeader)) {
                    throw new BadRequestHttpException('Invalid Content-Length header.');
                } else {
                    $contentLength = (int) $contentLengthHeader;
                }

                if ($contentLength > $this->maxBodySizeBytes) {
                    throw new PayloadTooLargeHttpException(
                        sprintf(
                            'Payload too large: Content-Length of %d bytes exceeds maximum limit of %d bytes.',
                            $contentLength,
                            $this->maxBodySizeBytes,
                        )
                    );
                }
            }

            $stream = $request->getBody();

            // 2. Stream metadata check (if available)
            $streamSize = $stream->getSize();
            if ($streamSize !== null && $streamSize > $this->maxBodySizeBytes) {
                throw new PayloadTooLargeHttpException(
                    sprintf(
                        'Payload too large: Body size of %d bytes exceeds maximum limit of %d bytes.',
                        $streamSize,
                        $this->maxBodySizeBytes,
                    )
                );
            }

            // 3. Bounded stream reading to protect against chunked transfer or missing Content-Length
            if ($stream->isSeekable()) {
                $stream->rewind();
            }

            $rawBody = '';
            while (!$stream->eof()) {
                $chunk = $stream->read(8192);
                $rawBody .= $chunk;

                if (strlen($rawBody) > $this->maxBodySizeBytes) {
                    throw new PayloadTooLargeHttpException(
                        sprintf(
                            'Payload too large: Body exceeds maximum limit of %d bytes.',
                            $this->maxBodySizeBytes,
                        )
                    );
                }
            }

            if ($stream->isSeekable()) {
                $stream->rewind();
            }

            if (trim($rawBody) !== '') {
                try {
                    $parsed = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
                } catch (JsonException $e) {
                    throw new BadRequestHttpException('Malformed JSON payload: ' . $e->getMessage(), previous: $e);
                }

                if (!is_array($parsed)) {
                    throw new BadRequestHttpException('Malformed JSON payload: Body must be a JSON object or array.');
                }

                $request = $request->withParsedBody($parsed);
            } else {
                $request = $request->withParsedBody([]);
            }
        }

        return $handler->handle($request);
    }

    /**
     * Check whether the Content-Type header matches JSON or JSON API formats.
     */
    private function isJsonContentType(string $contentType): bool
    {
        $mediaType = strtolower(trim(explode(';', $contentType, 2)[0]));

        return in_array($mediaType, [
            'application/json',
            'application/vnd.api+json',
        ], true);
    }
}
