<?php

declare(strict_types=1);

namespace Befit\Controller;

use Befit\Http\ApiResponse;
use Befit\Service\PaymentService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class AdminPaymentController extends BaseController
{
    public function __construct(private readonly PaymentService $payments)
    {
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        return ApiResponse::success($response, $this->payments->adminForUser((int) $args['id']));
    }

    public function create(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        return ApiResponse::success(
            $response,
            $this->payments->record((int) $args['id'], $this->body($request), $this->userId($request)),
            'Payment recorded successfully.',
            201
        );
    }
}
