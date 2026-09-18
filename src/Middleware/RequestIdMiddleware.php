<?php

declare(strict_types=1);

namespace Befit\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class RequestIdMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $incoming = trim($request->getHeaderLine('X-Request-ID'));
        $requestId = preg_match('/^[A-Za-z0-9._-]{8,100}$/', $incoming)
            ? $incoming
            : bin2hex(random_bytes(16));

        return $handler->handle($request->withAttribute('request_id', $requestId))
            ->withHeader('X-Request-ID', $requestId);
    }
}
