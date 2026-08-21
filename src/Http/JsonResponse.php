<?php

declare(strict_types=1);

namespace Micro\Http;

use JsonException;
use Micro\Exception\FrameworkException;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\Stream;
use Psr\Http\Message\ResponseInterface;

final class JsonResponse
{
    /**
     * Create a standard JSON PSR-7 Response.
     *
     * @param mixed $data
     * @param int $statusCode
     * @param array<string, string|list<string>> $headers
     */
    public static function create(mixed $data = null, int $statusCode = 200, array $headers = []): ResponseInterface
    {
        try {
            $json = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (JsonException $e) {
            throw new FrameworkException('Failed to JSON encode response data: ' . $e->getMessage(), 0, $e);
        }

        $headers['Content-Type'] = 'application/json';

        return new Response(
            status: $statusCode,
            headers: $headers,
            body: Stream::create($json),
        );
    }

    /**
     * Return a 200 OK JSON response.
     *
     * @param mixed $data
     * @param array<string, string|list<string>> $headers
     */
    public static function ok(mixed $data = null, array $headers = []): ResponseInterface
    {
        return self::create($data, 200, $headers);
    }

    /**
     * Return a 201 Created JSON response.
     *
     * @param mixed $data
     * @param array<string, string|list<string>> $headers
     */
    public static function created(mixed $data = null, array $headers = []): ResponseInterface
    {
        return self::create($data, 201, $headers);
    }

    /**
     * Return a standard JSON error response.
     *
     * @param string $message
     * @param int $statusCode
     * @param array<string, string|list<string>> $headers
     */
    public static function error(string $message, int $statusCode = 400, array $headers = []): ResponseInterface
    {
        return self::create(
            data: [
                'error' => [
                    'code' => $statusCode,
                    'message' => $message,
                ],
            ],
            statusCode: $statusCode,
            headers: $headers,
        );
    }

    /**
     * Return a 204 No Content response.
     *
     * @param array<string, string|list<string>> $headers
     */
    public static function noContent(array $headers = []): ResponseInterface
    {
        return new Response(status: 204, headers: $headers);
    }
}
