<?php

declare(strict_types=1);

namespace ViewMend\Contracts\Http;

use ViewMend\Core\Http\HttpRequest;
use ViewMend\Core\Http\HttpResponse;

interface TransportInterface
{
    public function send(HttpRequest $request): HttpResponse;
}
