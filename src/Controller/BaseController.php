<?php

declare(strict_types=1);

namespace Befit\Controller;

use Befit\Exception\ApiException;
use Psr\Http\Message\ServerRequestInterface;

abstract class BaseController
{
    protected function body(ServerRequestInterface $request): array
    {
        $body = $request->getParsedBody();
        if ($body === null) {
            return [];
        }
        if (!is_array($body)) {
            throw ApiException::validation(['body' => ['The request body must be a JSON object.']]);
        }
        return $body;
    }

    protected function user(ServerRequestInterface $request): array
    {
        $user = $request->getAttribute('auth_user');
        if (!is_array($user) || empty($user['id'])) {
            throw ApiException::unauthorized();
        }
        return $user;
    }

    protected function userId(ServerRequestInterface $request): int
    {
        return (int) $this->user($request)['id'];
    }
}
