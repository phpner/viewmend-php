<?php

declare(strict_types=1);

namespace ViewMend\Tests\Support;

use LogicException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class SequenceHttpClient implements ClientInterface
{
    /** @var list<ResponseInterface|ClientExceptionInterface> */
    private array $sequence;

    /** @var list<RequestInterface> */
    public array $requests = [];

    /** @param list<ResponseInterface|ClientExceptionInterface> $sequence */
    public function __construct(array $sequence)
    {
        $this->sequence = $sequence;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;
        $next = array_shift($this->sequence);

        if ($next === null) {
            throw new LogicException('The fake HTTP response sequence is exhausted.');
        }

        if ($next instanceof ClientExceptionInterface) {
            throw $next;
        }

        return $next;
    }

    public function isExhausted(): bool
    {
        return $this->sequence === [];
    }
}
