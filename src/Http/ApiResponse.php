<?php

declare(strict_types=1);

namespace Befit\Http;

use Psr\Http\Message\ResponseInterface;

final class ApiResponse
{
    public static function success(
        ResponseInterface $response,
        mixed $data = null,
        string $message = 'OK',
        int $status = 200,
        array $meta = []
    ): ResponseInterface {
        return self::write($response, [
            'success' => true,
            'data' => $data,
            'message' => $message,
            'errors' => [],
            'meta' => (object) $meta,
        ], $status);
    }

    public static function error(
        ResponseInterface $response,
        string $message,
        int $status,
        array $errors = [],
        string $code = 'API_ERROR'
    ): ResponseInterface {
        return self::write($response, [
            'success' => false,
            'data' => null,
            'message' => $message,
            'errors' => $errors,
            'code' => $code,
        ], $status);
    }

    private static function write(ResponseInterface $response, array $payload, int $status): ResponseInterface
    {
        $response->getBody()->write((string) json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        ));

        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json; charset=utf-8');
    }
}
