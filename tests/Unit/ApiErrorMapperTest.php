<?php

declare(strict_types=1);

namespace ViewMend\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ViewMend\Core\Exception\ApiException;
use ViewMend\Core\Exception\AuthenticationException;
use ViewMend\Core\Exception\EndpointDisabledException;
use ViewMend\Core\Exception\PayloadTooLargeException;
use ViewMend\Core\Exception\RateLimitException;
use ViewMend\Core\Exception\ServerException;
use ViewMend\Core\Exception\UnexpectedResponseException;
use ViewMend\Core\Exception\UnprocessableEventException;
use ViewMend\Core\Http\ApiErrorMapper;
use ViewMend\Core\Http\HttpResponse;

final class ApiErrorMapperTest extends TestCase
{
    /** @param class-string<ApiException> $expectedClass */
    #[DataProvider('responses')]
    public function testMapsHttpErrors(int $status, string $expectedClass): void
    {
        $response = new HttpResponse($status, '{"message":"server detail"}', [
            'X-ViewMend-Delivery' => 'delivery-1',
            'Retry-After' => '5',
        ]);

        $exception = (new ApiErrorMapper())->fromResponse($response);

        self::assertInstanceOf($expectedClass, $exception);
        self::assertSame($status, $exception->statusCode);
        self::assertSame('delivery-1', $exception->deliveryId);
        self::assertStringNotContainsString('server detail', $exception->getMessage());
    }

    /** @return iterable<string, array{int, class-string<ApiException>}> */
    public static function responses(): iterable
    {
        yield '401' => [401, AuthenticationException::class];
        yield '410' => [410, EndpointDisabledException::class];
        yield '413' => [413, PayloadTooLargeException::class];
        yield '422' => [422, UnprocessableEventException::class];
        yield '429' => [429, RateLimitException::class];
        yield '500' => [500, ServerException::class];
        yield '503' => [503, ServerException::class];
        yield '404' => [404, UnexpectedResponseException::class];
    }

    public function testRateLimitCarriesRetryAfterWithoutResponseBody(): void
    {
        $exception = (new ApiErrorMapper())->fromResponse(new HttpResponse(
            429,
            '{"message":"do not expose"}',
            ['Retry-After' => '9'],
        ));

        self::assertInstanceOf(RateLimitException::class, $exception);
        self::assertSame('9', $exception->retryAfter);
        self::assertStringNotContainsString('do not expose', $exception->getMessage());
    }
}
