<?php

declare(strict_types=1);

namespace Befit\Controller;

use Befit\Http\ApiResponse;
use Befit\Service\SettingsService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class BookingRulesController extends BaseController
{
    public function __construct(private readonly SettingsService $settings)
    {
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return ApiResponse::success($response, $this->settings->memberRules());
    }
}
