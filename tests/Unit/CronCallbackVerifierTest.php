<?php

declare(strict_types=1);

namespace ViewMend\Tests\Unit;

use DateTimeImmutable;
use JsonException;
use PHPUnit\Framework\TestCase;
use ViewMend\Exception\CallbackVerificationException;
use ViewMend\Cron\Callback;
use ViewMend\Cron\CallbackVerifier;
use ViewMend\ViewMend;

final class CronCallbackVerifierTest extends TestCase
{
    /** @throws JsonException */
    public function testCronClientVerifiesCallbackWithTheSameToken(): void
    {
        $timestamp = (string) time();
        $body = json_encode([
            'type' => Callback::TYPE_RUN,
            'run_id' => 'run_' . str_repeat('b', 26),
            'connection_id' => 'pcn_' . str_repeat('a', 26),
            'job_id' => 'cron_' . str_repeat('c', 26),
            'scheduled_at' => '2026-08-21T09:00:00+00:00',
            'attempt' => 1,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        $callback = ViewMend::client(token: $this->token())
            ->cron()
            ->verifyCallback([
                'X-ViewMend-Request-Id' => 'run_' . str_repeat('b', 26),
                'X-ViewMend-Timestamp' => $timestamp,
                'X-ViewMend-Signature' => $this->signature($timestamp, $body),
            ], $body);

        self::assertTrue($callback->isRun());
        self::assertSame('cron_' . str_repeat('c', 26), $callback->jobId);
    }

    /** @throws JsonException */
    public function testVerifiesSignedCallbackAndBuildsChallengeResponse(): void
    {
        [$verifier, $headers, $body] = $this->callbackFixture(Callback::TYPE_VERIFICATION, true);

        $callback = $verifier->verify($headers, $body);

        self::assertTrue($callback->isVerification());
        self::assertFalse($callback->isRun());
        self::assertSame('run_' . str_repeat('b', 26), $callback->runId);
        self::assertSame(1, $callback->attempt);
        self::assertSame(
            ['challenge' => str_repeat('V', 64)],
            json_decode($callback->verificationResponseBody(), true, 16, JSON_THROW_ON_ERROR),
        );
    }

    public function testAcceptsCaseInsensitiveSingleValueHeadersForRun(): void
    {
        [$verifier, $headers, $body] = $this->callbackFixture(Callback::TYPE_RUN, false);
        $headers = [
            'x-viewmend-request-id' => [$headers['X-ViewMend-Request-Id']],
            'x-viewmend-timestamp' => [$headers['X-ViewMend-Timestamp']],
            'x-viewmend-signature' => [$headers['X-ViewMend-Signature']],
        ];

        $callback = $verifier->verify($headers, $body);

        self::assertTrue($callback->isRun());
        self::assertNull($callback->challenge);
    }

    public function testRejectsInvalidSignatureWithoutLeakingSecretOrBody(): void
    {
        [$verifier, $headers, $body, $secret] = $this->callbackFixture(Callback::TYPE_RUN, false);
        $headers['X-ViewMend-Signature'] = 'v1=' . str_repeat('0', 64);

        try {
            $verifier->verify($headers, $body);
            self::fail('Expected callback verification to fail.');
        } catch (CallbackVerificationException $exception) {
            self::assertStringNotContainsString($secret, $exception->getMessage());
            self::assertStringNotContainsString($body, $exception->getMessage());
            self::assertStringNotContainsString($secret, print_r($verifier, true));
        }
    }

    public function testRejectsStaleTimestampAndMismatchedRequestId(): void
    {
        [$verifier, $headers, $body] = $this->callbackFixture(Callback::TYPE_RUN, false);
        $headers['X-ViewMend-Timestamp'] = '1787298000';
        $headers['X-ViewMend-Signature'] = $this->signature($headers['X-ViewMend-Timestamp'], $body);

        $this->expectException(CallbackVerificationException::class);
        $verifier->verify($headers, $body);
    }

    public function testRejectsRequestIdThatDoesNotMatchSignedBody(): void
    {
        [$verifier, $headers, $body] = $this->callbackFixture(Callback::TYPE_RUN, false);
        $headers['X-ViewMend-Request-Id'] = 'run_' . str_repeat('x', 26);

        $this->expectException(CallbackVerificationException::class);
        $verifier->verify($headers, $body);
    }

    /**
     * @return array{CallbackVerifier, array<string, string>, string, string}
     */
    private function callbackFixture(string $type, bool $withChallenge): array
    {
        $timestamp = '1787302800';
        $payload = [
            'type' => $type,
            'run_id' => 'run_' . str_repeat('b', 26),
            'connection_id' => 'pcn_' . str_repeat('a', 26),
            'job_id' => 'cron_' . str_repeat('c', 26),
            'scheduled_at' => '2026-08-21T09:00:00+00:00',
            'attempt' => 1,
        ];

        if ($withChallenge) {
            $payload['challenge'] = str_repeat('V', 64);
        }

        $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $secret = str_repeat('S', 64);

        return [
            CallbackVerifier::fromToken(
                $this->token(),
                new DateTimeImmutable('2026-08-21T09:00:00+00:00'),
            ),
            [
                'X-ViewMend-Request-Id' => 'run_' . str_repeat('b', 26),
                'X-ViewMend-Timestamp' => $timestamp,
                'X-ViewMend-Signature' => $this->signature($timestamp, $body),
            ],
            $body,
            $secret,
        ];
    }

    private function signature(string $timestamp, string $body): string
    {
        return 'v1=' . hash_hmac('sha256', $timestamp . '.' . $body, str_repeat('S', 64));
    }

    private function token(): string
    {
        return 'vmcron1_pcn_' . str_repeat('a', 26) . '_' . str_repeat('A', 64) . '_' . str_repeat('S', 64);
    }
}
