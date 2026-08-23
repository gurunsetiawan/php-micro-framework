<?php

declare(strict_types=1);

namespace Micro\Middleware;

use JsonException;
use Micro\Exception\BadRequestHttpException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class JsonBodyParserMiddleware implements MiddlewareInterface
{
    /**
     * Process an incoming server request and parse JSON body if applicable.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $contentType = $request->getHeaderLine('Content-Type');

        if ($this->isJsonContentType($contentType)) {
            $rawBody = (string) $request->getBody();

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
