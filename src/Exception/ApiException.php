<?php

declare(strict_types=1);

namespace Befit\Exception;

use RuntimeException;

final class ApiException extends RuntimeException
{
    public function __construct(
        public readonly int $status,
        string $message,
        public readonly array $errors = [],
        public readonly string $errorCode = 'API_ERROR',
        public readonly array $headers = []
    ) {
        parent::__construct($message, $status);
    }

    public static function validation(array $errors, string $message = 'Validation failed.'): self
    {
        return new self(422, $message, $errors, 'VALIDATION_ERROR');
    }

    public static function unauthorized(string $message = 'Authentication required.'): self
    {
        return new self(401, $message, [], 'UNAUTHORIZED');
    }

    public static function forbidden(string $message = 'You are not allowed to perform this action.'): self
    {
        return new self(403, $message, [], 'FORBIDDEN');
    }

    public static function notFound(string $message = 'Resource not found.'): self
    {
        return new self(404, $message, [], 'NOT_FOUND');
    }

    public static function conflict(string $message): self
    {
        return new self(409, $message, [], 'CONFLICT');
    }

    public static function tooManyRequests(
        string $message = 'Too many requests. Please try again later.',
        ?int $retryAfter = null
    ): self {
        $headers = [];
        if ($retryAfter !== null) {
            $headers['Retry-After'] = (string) max(1, $retryAfter);
        }

        return new self(429, $message, [], 'RATE_LIMITED', $headers);
    }
}
