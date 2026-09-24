<?php

/**
 * Shared request creation for Ppio models.
 *
 * @package Ppio\AiProvider
 */

declare(strict_types=1);

namespace Ppio\AiProvider\Models;

use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use Ppio\AiProvider\Provider\PpioProvider;
use Ppio\AiProvider\Util\PpioConfig;

/**
 * Builds requests against the Ppio API.
 *
 * Every Ppio endpoint authenticates with `Authorization: Bearer <key>`, which the SDK's default API
 * key authentication already applies, so no custom authentication class is needed.
 */
trait PpioRequestTrait
{
    /**
     * Creates a request object for the Ppio API.
     *
     * Satisfies the abstract `createRequest()` of the OpenAI-compatible base class.
     *
     * @param HttpMethodEnum $method The HTTP method.
     * @param string $path The API endpoint path, relative to the base URL.
     * @param array<string, string|list<string>> $headers The request headers.
     * @param string|array<string, mixed>|null $data The request data.
     * @return Request The request object.
     */
    protected function createRequest(
        HttpMethodEnum $method,
        string $path,
        array $headers = [],
        $data = null
    ): Request {
        $headers['User-Agent'] = PpioConfig::getUserAgent();

        return new Request(
            $method,
            PpioProvider::url($path),
            $headers,
            $data,
            $this->getRequestOptions()
        );
    }
}
