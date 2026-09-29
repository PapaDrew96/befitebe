<?php

declare(strict_types=1);

namespace Befit\Middleware;

use Befit\Exception\ApiException;
use Befit\Repository\RateLimitRepository;
use Befit\Support\ClientIpResolver;
use DateTimeImmutable;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class RateLimitMiddleware implements MiddlewareInterface
{
    public const STRATEGY_IP = 'ip';
    public const STRATEGY_SUBJECT = 'subject';
    public const STRATEGY_IP_SUBJECT = 'ip_subject';

    public function __construct(
        private readonly RateLimitRepository $repository,
        private readonly ClientIpResolver $clientIps,
        private readonly string $appKey,
        private readonly string $bucket,
        private readonly int $limit,
        private readonly int $windowSeconds,
        private readonly string $strategy = self::STRATEGY_IP
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $ip = $this->clientIps->resolve($request);
        $subject = $this->subject($request);

        $identity = match ($this->strategy) {
            self::STRATEGY_SUBJECT => $subject !== '' ? 'subject|' . $subject : 'ip|' . $ip,
            self::STRATEGY_IP_SUBJECT => 'ip|' . $ip . '|subject|' . $subject,
            default => 'ip|' . $ip,
        };

        $material = $this->bucket . '|' . $identity;
        $keyHash = $this->appKey !== ''
            ? hash_hmac('sha256', $material, $this->appKey)
            : hash('sha256', $material);

        $result = $this->repository->consume(
            $keyHash,
            $this->limit,
            $this->windowSeconds,
            new DateTimeImmutable()
        );

        if (!$result['allowed']) {
            throw ApiException::tooManyRequests(
                'Too many attempts. Please try again later.',
                (int) $result['retry_after']
            );
        }

        return $handler->handle($request)
            ->withHeader('X-RateLimit-Limit', (string) $this->limit)
            ->withHeader('X-RateLimit-Remaining', (string) $result['remaining']);
    }

    private function subject(ServerRequestInterface $request): string
    {
        $body = $request->getParsedBody();
        if (!is_array($body) || !isset($body['login'])) {
            return '';
        }

        return mb_strtolower(trim((string) $body['login']));
    }
}
