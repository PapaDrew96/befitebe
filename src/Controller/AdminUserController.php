<?php

declare(strict_types=1);

namespace Befit\Controller;

use Befit\Http\ApiResponse;
use Befit\Service\UserService;
use Befit\Service\PaymentService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class AdminUserController extends BaseController
{
    public function __construct(
        private readonly UserService $users,
        private readonly PaymentService $payments
    ) {
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $data = $this->users->paginate($request->getQueryParams());
        $userIds = array_map(static fn (array $user): int => (int) $user['id'], $data['items']);
        $paymentStatuses = $this->payments->currentStatuses($userIds);
        foreach ($data['items'] as &$user) {
            $payment = $paymentStatuses[(int) $user['id']] ?? ['paid_until' => null, 'payment_status' => 'unpaid'];
            $user['paid_until'] = $payment['paid_until'];
            $user['payment_status'] = $payment['payment_status'];
        }
        unset($user);
        return ApiResponse::success($response, $data);
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->users->get((int) $args['id']);
        $payment = $this->payments->currentStatuses([(int) $args['id']])[(int) $args['id']] ?? ['paid_until' => null, 'payment_status' => 'unpaid'];
        $user['paid_until'] = $payment['paid_until'];
        $user['payment_status'] = $payment['payment_status'];
        return ApiResponse::success($response, $user);
    }

    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return ApiResponse::success(
            $response,
            $this->users->create($this->body($request), $this->userId($request)),
            'User created successfully.',
            201
        );
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        return ApiResponse::success(
            $response,
            $this->users->updateAdmin((int) $args['id'], $this->body($request), $this->userId($request)),
            'User updated successfully.'
        );
    }
}
