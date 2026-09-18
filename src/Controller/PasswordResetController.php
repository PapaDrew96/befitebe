<?php

declare(strict_types=1);

namespace Befit\Controller;

use Befit\Http\ApiResponse;
use Befit\Service\PasswordResetService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class PasswordResetController extends BaseController
{
    public function __construct(private readonly PasswordResetService $passwords) {}
    public function forgot(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface { return ApiResponse::success($response, $this->passwords->request($this->body($request)), 'If the account exists, password reset instructions have been created.'); }
    public function reset(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface { $this->passwords->reset($this->body($request)); return ApiResponse::success($response, null, 'Password reset successfully.'); }
}
