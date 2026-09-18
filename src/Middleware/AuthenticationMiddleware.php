<?php

declare(strict_types=1);

namespace Befit\Middleware;

use Befit\Exception\ApiException;
use Befit\Repository\TokenRepository;
use DateTimeImmutable;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class AuthenticationMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly TokenRepository $tokens,
        private readonly ?string $requiredRole = null
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $header = trim($request->getHeaderLine('Authorization'));
        if (!preg_match('/^Bearer\s+([a-f0-9]{64})$/i', $header, $matches)) {
            throw ApiException::unauthorized('A valid Bearer token is required.');
        }

        $tokenHash = hash('sha256', strtolower($matches[1]));
        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');
        $user = $this->tokens->findValidUserByHash($tokenHash, $now);

        if (!$user || $user['status'] !== 'active') {
            throw ApiException::unauthorized('Your session is invalid or has expired.');
        }

        if ($this->requiredRole !== null && $user['role'] !== $this->requiredRole) {
            throw ApiException::forbidden('Administrator access is required.');
        }

        if (!empty($user['must_change_password'])) {
            $path = rtrim($request->getUri()->getPath(), '/');
            $allowedSuffixes = [
                '/api/v1/auth/logout',
                '/api/v1/auth/me',
                '/api/v1/profile',
                '/api/v1/profile/password',
            ];
            $allowed = false;
            foreach ($allowedSuffixes as $suffix) {
                if (str_ends_with($path, $suffix)) {
                    $allowed = true;
                    break;
                }
            }
            if (!$allowed) {
                throw ApiException::forbidden('You must change your temporary password before using the application.');
            }
        }

        $this->tokens->touch($tokenHash, $now);

        return $handler->handle(
            $request
                ->withAttribute('auth_user', $user)
                ->withAttribute('auth_token_hash', $tokenHash)
        );
    }
}
