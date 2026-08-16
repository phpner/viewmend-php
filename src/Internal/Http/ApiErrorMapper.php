<?php

declare(strict_types=1);

namespace ViewMend\Internal\Http;

use ViewMend\Exception\ApiException;
use ViewMend\Exception\AuthenticationException;
use ViewMend\Exception\EndpointDisabledException;
use ViewMend\Exception\PayloadTooLargeException;
use ViewMend\Exception\RateLimitException;
use ViewMend\Exception\ServerException;
use ViewMend\Exception\UnexpectedResponseException;
use ViewMend\Exception\UnprocessableEventException;

/** @internal */
final class ApiErrorMapper
{
    public function fromResponse(HttpResponse $response): ApiException
    {
        $deliveryId = $response->headerLine('X-ViewMend-Delivery');

        return match ($response->statusCode) {
            401 => new AuthenticationException(
                'ViewMend rejected the API credentials.',
                401,
                $deliveryId,
            ),
            410 => new EndpointDisabledException(
                'The ViewMend event endpoint is disabled.',
                410,
                $deliveryId,
            ),
            413 => new PayloadTooLargeException(
                'The ViewMend event payload is too large.',
                413,
                $deliveryId,
            ),
            422 => new UnprocessableEventException(
                'ViewMend could not apply the event payload.',
                422,
                $deliveryId,
            ),
            429 => new RateLimitException(
                'The ViewMend API rate limit was reached.',
                429,
                $deliveryId,
                $response->headerLine('Retry-After'),
            ),
            default => $this->unexpectedOrServer($response, $deliveryId),
        };
    }

    private function unexpectedOrServer(HttpResponse $response, ?string $deliveryId): ApiException
    {
        if ($response->statusCode >= 500) {
            return new ServerException(
                'ViewMend could not process the API request.',
                $response->statusCode,
                $deliveryId,
            );
        }

        return new UnexpectedResponseException(
            'ViewMend returned an unexpected HTTP status.',
            $response->statusCode,
            $deliveryId,
        );
    }
}
