<?php

declare(strict_types=1);

namespace Befit\Middleware;

use Befit\Http\ApiResponse;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class CorsMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly array $allowedOrigins
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $origin = trim($request->getHeaderLine('Origin'));
        $allowedOrigin = $this->allowedOrigin($origin);
        $isPreflight = strtoupper($request->getMethod()) === 'OPTIONS';

        if ($origin !== '' && $allowedOrigin === null) {
            return ApiResponse::error(
                $this->responseFactory->createResponse(),
                'Origin is not allowed.',
                403,
                [],
                'CORS_ORIGIN_DENIED'
            );
        }

        $response = $isPreflight
            ? $this->responseFactory->createResponse(204)
            : $handler->handle($request);

        if ($allowedOrigin !== null) {
            $response = $response
                ->withHeader('Access-Control-Allow-Origin', $allowedOrigin)
                ->withAddedHeader('Vary', 'Origin')
                ->withHeader('Access-Control-Allow-Headers', 'Authorization, Content-Type, Accept, X-Request-ID')
                ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
                ->withHeader('Access-Control-Max-Age', '600');
        }

        return $response;
    }

    private function allowedOrigin(string $origin): ?string
    {
        if ($origin === '') {
            return null;
        }

        if (in_array('*', $this->allowedOrigins, true)) {
            return '*';
        }

        return in_array($origin, $this->allowedOrigins, true) ? $origin : null;
    }
}
