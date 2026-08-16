<?php

declare(strict_types=1);

namespace ViewMend\Internal\Contracts\Http;

use ViewMend\Internal\Http\HttpRequest;
use ViewMend\Internal\Http\HttpResponse;

interface TransportInterface
{
    public function send(HttpRequest $request): HttpResponse;
}
