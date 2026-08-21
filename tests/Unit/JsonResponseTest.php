<?php

declare(strict_types=1);

namespace Micro\Tests\Unit;

use Micro\Http\JsonResponse;
use PHPUnit\Framework\TestCase;

final class JsonResponseTest extends TestCase
{
    public function testOkResponse(): void
    {
        $response = JsonResponse::ok(['hello' => 'dunia', 'unicode' => '✓']);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertSame('{"hello":"dunia","unicode":"✓"}', (string) $response->getBody());
    }

    public function testCreatedResponse(): void
    {
        $response = JsonResponse::created(['id' => '123'], ['Location' => '/items/123']);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('/items/123', $response->getHeaderLine('Location'));
    }

    public function testErrorResponse(): void
    {
        $response = JsonResponse::error('Invalid email address', 422);

        $this->assertSame(422, $response->getStatusCode());
        $payload = json_decode((string) $response->getBody(), true);
        $this->assertSame(422, $payload['error']['code']);
        $this->assertSame('Invalid email address', $payload['error']['message']);
    }

    public function testNoContentResponse(): void
    {
        $response = JsonResponse::noContent(['X-Action' => 'deleted']);

        $this->assertSame(204, $response->getStatusCode());
        $this->assertSame('', (string) $response->getBody());
        $this->assertSame('deleted', $response->getHeaderLine('X-Action'));
    }
}
