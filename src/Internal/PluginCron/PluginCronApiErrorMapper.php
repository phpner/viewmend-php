<?php

declare(strict_types=1);

namespace ViewMend\Internal\PluginCron;

use ViewMend\Exception\ApiException;
use ViewMend\Exception\AuthenticationException;
use ViewMend\Exception\EndpointDisabledException;
use ViewMend\Exception\RateLimitException;
use ViewMend\Exception\ServerException;
use ViewMend\Exception\UnexpectedResponseException;
use ViewMend\Exception\UnprocessableRegistrationException;
use ViewMend\Internal\Http\HttpResponse;

/** @internal */
final class PluginCronApiErrorMapper
{
    public function fromResponse(HttpResponse $response): ApiException
    {
        $requestId = $response->headerLine('X-Request-Id');

        return match ($response->statusCode) {
            401 => new AuthenticationException(
                'ViewMend rejected the Plugin Cron connection key.',
                401,
                $requestId,
            ),
            410 => new EndpointDisabledException(
                'The ViewMend Plugin Cron connection is disabled.',
                410,
                $requestId,
            ),
            422 => new UnprocessableRegistrationException(
                'ViewMend rejected the Plugin Cron registration.',
                422,
                $requestId,
            ),
            429 => new RateLimitException(
                'The ViewMend API rate limit was reached.',
                429,
                $requestId,
                $response->headerLine('Retry-After'),
            ),
            default => $this->unexpectedOrServer($response, $requestId),
        };
    }

    private function unexpectedOrServer(HttpResponse $response, ?string $requestId): ApiException
    {
        if ($response->statusCode >= 500) {
            return new ServerException(
                'ViewMend could not process the Plugin Cron request.',
                $response->statusCode,
                $requestId,
            );
        }

        return new UnexpectedResponseException(
            'ViewMend returned an unexpected Plugin Cron HTTP status.',
            $response->statusCode,
            $requestId,
        );
    }
}
