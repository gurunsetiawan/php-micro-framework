<?php

declare(strict_types=1);

namespace Micro\Tests\Integration;

use Exception;
use Micro\Application\App;
use Micro\Exception\NotFoundHttpException;
use Micro\Exception\UnprocessableEntityHttpException;
use Micro\Http\JsonResponse;
use Micro\Middleware\AccessLogMiddleware;
use Micro\Middleware\ErrorHandlerMiddleware;
use Micro\Middleware\RequestIdMiddleware;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\AbstractLogger;
use Stringable;

final class ObservabilityPipelineIntegrationTest extends TestCase
{
    private function createPipeline(array &$logRecords): App
    {
        $app = App::create();

        $logger = new class ($logRecords) extends AbstractLogger {
            public function __construct(private array &$records) {}
            public function log($level, string|Stringable $message, array $context = []): void
            {
                $this->records[] = [
                    'level' => (string) $level,
                    'message' => (string) $message,
                    'context' => $context,
                ];
            }
        };

        // Canonical observability order: RequestId -> AccessLog -> ErrorHandler -> Router
        $app->add(new RequestIdMiddleware());
        $app->add(new AccessLogMiddleware($logger));
        $app->add(new ErrorHandlerMiddleware(debug: false, logger: $logger));

        // Define routes
        $app->get('/api/success', static fn(): ResponseInterface => JsonResponse::ok(['hello' => 'world']));
        $app->post('/api/validate', static function (ServerRequestInterface $request): ResponseInterface {
            throw new UnprocessableEntityHttpException('Validation failed: missing field.');
        });
        $app->get('/api/error', static function (): ResponseInterface {
            throw new Exception('Fatal crash in application logic');
        });

        return $app;
    }

    public function testSuccessPathLogsWithRequestIdAndReturnsHeader(): void
    {
        $logs = [];
        $app = $this->createPipeline($logs);

        $request = (new ServerRequest('GET', '/api/success'))
            ->withHeader(RequestIdMiddleware::HEADER_NAME, 'trace-client-id-001');

        $response = $app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('trace-client-id-001', $response->getHeaderLine(RequestIdMiddleware::HEADER_NAME));

        // Assert access log was emitted
        $this->assertCount(1, $logs);
        $this->assertSame('info', $logs[0]['level']);
        $this->assertSame('HTTP request completed', $logs[0]['message']);
        $this->assertSame('trace-client-id-001', $logs[0]['context']['request_id']);
        $this->assertSame('GET', $logs[0]['context']['method']);
        $this->assertSame('/api/success', $logs[0]['context']['path']);
        $this->assertSame(200, $logs[0]['context']['status']);
        $this->assertIsFloat($logs[0]['context']['duration_ms']);
    }

    public function test4xxIsHandledAndLoggedWithCorrectStatusAndRequestId(): void
    {
        $logs = [];
        $app = $this->createPipeline($logs);

        $request = new ServerRequest('POST', '/api/validate');
        $response = $app->handle($request);

        $this->assertSame(422, $response->getStatusCode());
        $requestId = $response->getHeaderLine(RequestIdMiddleware::HEADER_NAME);
        $this->assertNotEmpty($requestId);

        // ErrorHandler logs warning + AccessLog logs warning
        $this->assertCount(2, $logs);

        // First log is from ErrorHandlerMiddleware
        $this->assertSame('warning', $logs[0]['level']);
        $this->assertSame('Validation failed: missing field.', $logs[0]['message']);

        // Second log is from AccessLogMiddleware
        $this->assertSame('warning', $logs[1]['level']);
        $this->assertSame('HTTP request completed', $logs[1]['message']);
        $this->assertSame($requestId, $logs[1]['context']['request_id']);
        $this->assertSame(422, $logs[1]['context']['status']);
    }

    public function test500IsHandledAndLoggedWithCorrectStatusAndRequestId(): void
    {
        $logs = [];
        $app = $this->createPipeline($logs);

        $request = new ServerRequest('GET', '/api/error');
        $response = $app->handle($request);

        $this->assertSame(500, $response->getStatusCode());
        $requestId = $response->getHeaderLine(RequestIdMiddleware::HEADER_NAME);
        $this->assertNotEmpty($requestId);

        $body = json_decode((string) $response->getBody(), true);
        $this->assertSame(500, $body['error']['code']);
        $this->assertSame('Internal Server Error', $body['error']['message']);

        // ErrorHandler logs error + AccessLog logs error
        $this->assertCount(2, $logs);

        // ErrorHandler log
        $this->assertSame('error', $logs[0]['level']);
        $this->assertSame('Fatal crash in application logic', $logs[0]['message']);

        // AccessLog log
        $this->assertSame('error', $logs[1]['level']);
        $this->assertSame('HTTP request completed', $logs[1]['message']);
        $this->assertSame($requestId, $logs[1]['context']['request_id']);
        $this->assertSame(500, $logs[1]['context']['status']);
    }

    public function testNotFoundRouteIsLoggedAtWarning(): void
    {
        $logs = [];
        $app = $this->createPipeline($logs);

        $request = new ServerRequest('GET', '/api/non-existent-route');
        $response = $app->handle($request);

        $this->assertSame(404, $response->getStatusCode());
        $requestId = $response->getHeaderLine(RequestIdMiddleware::HEADER_NAME);
        $this->assertNotEmpty($requestId);

        // AccessLog logs 404 warning
        $accessLogs = array_filter($logs, fn($l) => $l['message'] === 'HTTP request completed');
        $this->assertCount(1, $accessLogs);
        $accessLog = array_values($accessLogs)[0];
        $this->assertSame('warning', $accessLog['level']);
        $this->assertSame(404, $accessLog['context']['status']);
        $this->assertSame($requestId, $accessLog['context']['request_id']);
    }
}
