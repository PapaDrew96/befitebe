<?php

declare(strict_types=1);

namespace Befit\Middleware;

use Befit\Exception\ApiException;
use Befit\Repository\RateLimitRepository;
use DateTimeImmutable;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class RateLimitMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly RateLimitRepository $repository,
        private readonly string $bucket,
        private readonly int $limit,
        private readonly int $windowSeconds
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $server = $request->getServerParams();
        $ip = (string) ($server['REMOTE_ADDR'] ?? 'unknown');
        $body = $request->getParsedBody();
        $subject = is_array($body) && isset($body['login'])
            ? mb_strtolower(trim((string) $body['login']))
            : '';
        $keyHash = hash('sha256', $this->bucket . '|' . $ip . '|' . $subject);
        $result = $this->repository->consume(
            $keyHash,
            $this->limit,
            $this->windowSeconds,
            new DateTimeImmutable()
        );

        if (!$result['allowed']) {
            throw ApiException::tooManyRequests(
                'Too many attempts. Please retry in ' . $result['retry_after'] . ' seconds.'
            );
        }

        $response = $handler->handle($request);
        return $response
            ->withHeader('X-RateLimit-Limit', (string) $this->limit)
            ->withHeader('X-RateLimit-Remaining', (string) $result['remaining']);
    }
}
