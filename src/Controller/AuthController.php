<?php

declare(strict_types=1);

namespace Befit\Controller;

use Befit\Http\ApiResponse;
use Befit\Service\AuthService;
use Befit\Service\UserService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class AuthController extends BaseController
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly UserService $users
    ) {
    }

    public function login(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $tokenName = trim($request->getHeaderLine('User-Agent')) ?: 'pwa';
        return ApiResponse::success(
            $response,
            $this->auth->login($this->body($request), $tokenName),
            'Login successful.'
        );
    }

    public function logout(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->auth->logout(
            $this->userId($request),
            (string) $request->getAttribute('auth_token_hash')
        );
        return ApiResponse::success($response, null, 'Logged out successfully.');
    }

    public function me(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return ApiResponse::success($response, $this->users->get($this->userId($request)));
    }
}
