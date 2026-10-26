<?php

namespace App\Concerns;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Throwable;

trait CallsOpenMeteo
{
    /**
     * Build an Open-Meteo HTTP client that retries transient failures.
     */
    protected function openMeteo(string $baseUrl): PendingRequest
    {
        return Http::baseUrl($baseUrl)
            ->acceptJson()
            ->connectTimeout(3)
            ->timeout(5)
            ->retry([200, 500], 0, fn (Throwable $exception): bool => $exception instanceof ConnectionException
                || ($exception instanceof RequestException
                    && ($exception->response->serverError() || $exception->response->status() === 429)));
    }
}
