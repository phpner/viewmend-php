<?php

declare(strict_types=1);

namespace ViewMend\Internal\PluginCron;

use JsonException;
use ViewMend\Exception\ApiException;
use ViewMend\Exception\AuthenticationException;
use ViewMend\Exception\EndpointDisabledException;
use ViewMend\Exception\RateLimitException;
use ViewMend\Exception\ServerException;
use ViewMend\Exception\TokenScopeException;
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
            401 => $this->authentication($response, $requestId),
            410 => new EndpointDisabledException(
                'The ViewMend Cron connection is disabled.',
                410,
                $requestId,
            ),
            422 => new UnprocessableRegistrationException(
                'ViewMend rejected the Cron registration.',
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

    private function authentication(HttpResponse $response, ?string $requestId): AuthenticationException
    {
        if ($this->errorCode($response) === 'token_scope_invalid') {
            return new TokenScopeException(
                'This token is for the Site Tracker API and cannot be used with the Cron API. '
                    . 'Use the connection token issued in Integrations.',
                401,
                $requestId,
            );
        }

        return new AuthenticationException(
            'ViewMend rejected the Cron connection token.',
            401,
            $requestId,
        );
    }

    private function errorCode(HttpResponse $response): ?string
    {
        try {
            $document = json_decode($response->body, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (! is_array($document) || array_is_list($document)) {
            return null;
        }

        $error = $document['error'] ?? null;
        if (! is_array($error) || array_is_list($error)) {
            return null;
        }

        $code = $error['code'] ?? null;

        return is_string($code) ? $code : null;
    }

    private function unexpectedOrServer(HttpResponse $response, ?string $requestId): ApiException
    {
        if ($response->statusCode >= 500) {
            return new ServerException(
                'ViewMend could not process the Cron request.',
                $response->statusCode,
                $requestId,
            );
        }

        return new UnexpectedResponseException(
            'ViewMend returned an unexpected Cron HTTP status.',
            $response->statusCode,
            $requestId,
        );
    }
}
