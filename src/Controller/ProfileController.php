<?php

declare(strict_types=1);

namespace Befit\Controller;

use Befit\Http\ApiResponse;
use Befit\Service\UserService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class ProfileController extends BaseController
{
    public function __construct(private readonly UserService $users)
    {
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return ApiResponse::success($response, $this->users->get($this->userId($request)));
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return ApiResponse::success(
            $response,
            $this->users->updateProfile($this->userId($request), $this->body($request)),
            'Profile updated successfully.'
        );
    }

    public function changePassword(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->users->changePassword($this->userId($request), $this->body($request));
        return ApiResponse::success(
            $response,
            null,
            'Password changed successfully. Please log in again.'
        );
    }
}
