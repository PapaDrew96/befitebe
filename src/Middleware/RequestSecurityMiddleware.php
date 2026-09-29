<?php

declare(strict_types=1);

namespace Befit\Middleware;

use Befit\Http\ApiResponse;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class RequestSecurityMiddleware implements MiddlewareInterface
{
    /** @param string[] $allowedHosts */
    public function __construct(
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly array $allowedHosts,
        private readonly int $maxRequestBodyBytes,
        private readonly bool $requireJsonWrites
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $method = strtoupper($request->getMethod());

        if (in_array($method, ['TRACE', 'TRACK', 'CONNECT'], true)) {
            return $this->error(405, 'HTTP method not allowed.', 'METHOD_NOT_ALLOWED');
        }

        if ($request->hasHeader('X-HTTP-Method-Override') || $request->hasHeader('X-Method-Override')) {
            return $this->error(400, 'HTTP method override headers are not accepted.', 'BAD_REQUEST');
        }

        $host = strtolower(rtrim($request->getUri()->getHost(), '.'));
        if ($this->allowedHosts !== [] && ($host === '' || !in_array($host, $this->allowedHosts, true))) {
            return $this->error(400, 'Invalid request host.', 'INVALID_HOST');
        }

        $contentLength = trim($request->getHeaderLine('Content-Length'));
        if ($contentLength !== '' && ctype_digit($contentLength) && (int) $contentLength > $this->maxRequestBodyBytes) {
            return $this->error(413, 'Request body is too large.', 'PAYLOAD_TOO_LARGE');
        }

        $bodySize = $request->getBody()->getSize();
        if ($bodySize !== null && $bodySize > $this->maxRequestBodyBytes) {
            return $this->error(413, 'Request body is too large.', 'PAYLOAD_TOO_LARGE');
        }

        if ($this->requireJsonWrites && in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $hasBody = ($contentLength !== '' && (int) $contentLength > 0) || ($bodySize !== null && $bodySize > 0);
            if ($hasBody) {
                $contentType = strtolower(trim(explode(';', $request->getHeaderLine('Content-Type'), 2)[0]));
                $json = $contentType === 'application/json' || str_ends_with($contentType, '+json');
                if (!$json) {
                    return $this->error(415, 'Requests with a body must use application/json.', 'UNSUPPORTED_MEDIA_TYPE');
                }
            }
        }

        return $handler->handle($request);
    }

    private function error(int $status, string $message, string $code): ResponseInterface
    {
        return ApiResponse::error(
            $this->responseFactory->createResponse(),
            $message,
            $status,
            [],
            $code
        );
    }
}
