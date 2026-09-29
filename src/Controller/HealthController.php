<?php

declare(strict_types=1);

namespace Befit\Controller;

use Befit\Database\Database;
use Befit\Http\ApiResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

final class HealthController extends BaseController
{
    public function __construct(private readonly Database $database)
    {
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            if (!$this->database->ping()) {
                return ApiResponse::error(
                    $response,
                    'Service unavailable.',
                    503,
                    [],
                    'SERVICE_UNAVAILABLE'
                );
            }
        } catch (Throwable) {
            return ApiResponse::error(
                $response,
                'Service unavailable.',
                503,
                [],
                'SERVICE_UNAVAILABLE'
            );
        }

        return ApiResponse::success($response, [
            'status' => 'ok',
            'timestamp' => date(DATE_ATOM),
        ]);
    }
}
