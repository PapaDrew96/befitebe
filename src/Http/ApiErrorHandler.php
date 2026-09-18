<?php

declare(strict_types=1);

namespace Befit\Http;

use Befit\Exception\ApiException;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpException;
use Throwable;

final class ApiErrorHandler
{
    public function __construct(
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly LoggerInterface $logger,
        private readonly bool $debug
    ) {
    }

    public function __invoke(
        ServerRequestInterface $request,
        Throwable $exception,
        bool $displayErrorDetails,
        bool $logErrors,
        bool $logErrorDetails
    ): ResponseInterface {
        $response = $this->responseFactory->createResponse();

        if ($exception instanceof ApiException) {
            return ApiResponse::error(
                $response,
                $exception->getMessage(),
                $exception->status,
                $exception->errors,
                $exception->errorCode
            );
        }

        if ($exception instanceof HttpException) {
            $status = $exception->getCode() > 0 ? $exception->getCode() : 500;
            return ApiResponse::error($response, $exception->getMessage(), $status, [], 'HTTP_ERROR');
        }

        $this->logger->error('Unhandled API exception', [
            'request_id' => $request->getAttribute('request_id'),
            'exception' => $exception,
        ]);

        $errors = [];
        if ($this->debug || $displayErrorDetails) {
            $errors['exception'] = get_class($exception);
            $errors['detail'] = $exception->getMessage();
            $errors['file'] = $exception->getFile() . ':' . $exception->getLine();
        }

        return ApiResponse::error(
            $response,
            'An unexpected server error occurred.',
            500,
            $errors,
            'SERVER_ERROR'
        );
    }
}
