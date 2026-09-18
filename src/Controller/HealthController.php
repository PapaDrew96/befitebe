<?php

declare(strict_types=1);

namespace Befit\Controller;

use Befit\Database\Database;
use Befit\Http\ApiResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class HealthController extends BaseController
{
    public function __construct(private readonly Database $database)
    {
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return ApiResponse::success($response, [
            'status' => 'ok',
            'database' => $this->database->ping() ? 'ok' : 'error',
            'timestamp' => date(DATE_ATOM),
        ]);
    }
}
