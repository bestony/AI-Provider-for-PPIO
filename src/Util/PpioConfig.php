<?php

/**
 * PPIO provider configuration.
 *
 * @package Ppio\AiProvider
 */

declare(strict_types=1);

namespace Ppio\AiProvider\Util;

use WordPress\AiClient\AiClient;
use WordPress\AiClient\Providers\Http\DTO\RequestOptions;

/**
 * Reads optional environment or PHP constant overrides.
 */
final class PpioConfig
{
    public const VERSION = '1.0.0';
    public const PROVIDER_ID = 'ppio';
    public const DEFAULT_BASE_URL = 'https://api.ppio.com/openai/v1';
    public const DEFAULT_MODEL = 'deepseek/deepseek-v4-flash';

    /**
     * Resolves an environment variable first, then a PHP constant.
     *
     * @param string $name Configuration name.
     * @return string The value, or an empty string when unset.
     */
    public static function env(string $name): string
    {
        $value = getenv($name);
        if (is_string($value) && $value !== '') {
            return $value;
        }

        if (defined($name)) {
            $constant = constant($name);
            if (is_scalar($constant)) {
                return (string) $constant;
            }
        }

        return '';
    }

    public static function getBaseUrl(): string
    {
        $url = self::env('PPIO_BASE_URL');

        return $url === '' ? self::DEFAULT_BASE_URL : rtrim($url, '/');
    }

    public static function getDefaultModelId(): string
    {
        $model = self::env('PPIO_DEFAULT_MODEL');

        return $model === '' ? self::DEFAULT_MODEL : $model;
    }

    public static function getStructuredOutputMode(): string
    {
        $mode = strtolower(self::env('PPIO_STRUCTURED_OUTPUT'));

        return in_array($mode, ['json_schema', 'json_object', 'none'], true) ? $mode : 'json_schema';
    }

    /**
     * Whether all text models should be treated as vision capable.
     */
    public static function declaresImageInput(): bool
    {
        $modalities = strtolower(self::env('PPIO_MODEL_INPUT_MODALITIES'));
        if ($modalities === '') {
            return false;
        }

        return in_array('image', array_map('trim', explode(',', $modalities)), true);
    }

    public static function getRequestTimeout(): float
    {
        $value = self::env('PPIO_REQUEST_TIMEOUT');

        return $value === '' ? 120.0 : max(1.0, (float) $value);
    }

    public static function getConnectTimeout(): float
    {
        $value = self::env('PPIO_CONNECT_TIMEOUT');

        return $value === '' ? 10.0 : max(1.0, (float) $value);
    }

    /**
     * Gets the separate reasoning request flag.
     *
     * An explicit PPIO_SEPARATE_REASONING value wins. When unset, only the documented DeepSeek R1
     * Turbo model receives the flag. The null result means that no parameter should be sent.
     *
     * @param string $modelId Model ID.
     * @return bool|null The override or automatic model-specific value.
     */
    public static function getSeparateReasoning(string $modelId): ?bool
    {
        $configured = self::booleanOverride('PPIO_SEPARATE_REASONING');
        if ($configured !== null) {
            return $configured;
        }

        return strtolower($modelId) === 'deepseek/deepseek-r1-turbo' ? true : null;
    }

    /**
     * Gets the enable thinking request flag.
     *
     * @param string $modelId Model ID.
     * @return bool|null The override or automatic model-specific value.
     */
    public static function getEnableThinking(string $modelId): ?bool
    {
        $configured = self::booleanOverride('PPIO_ENABLE_THINKING');
        if ($configured !== null) {
            return $configured;
        }

        return strtolower($modelId) === 'deepseek/deepseek-v3.2-exp' ? true : null;
    }

    private static function booleanOverride(string $name): ?bool
    {
        $value = strtolower(trim(self::env($name)));
        if ($value === '') {
            return null;
        }

        if (in_array($value, ['1', 'true', 'yes', 'on'], true)) {
            return true;
        }

        if (in_array($value, ['0', 'false', 'no', 'off'], true)) {
            return false;
        }

        return null;
    }

    /**
     * Checks the AI Client registry without reading the Connectors option directly.
     */
    public static function hasCredentials(): bool
    {
        if (!class_exists(AiClient::class)) {
            return false;
        }

        $registry = AiClient::defaultRegistry();
        if (!$registry->hasProvider(self::PROVIDER_ID)) {
            return false;
        }

        return $registry->getProviderRequestAuthentication(self::PROVIDER_ID) !== null;
    }

    public static function createRequestOptions(): RequestOptions
    {
        $options = new RequestOptions();
        $options->setTimeout(self::getRequestTimeout());
        $options->setConnectTimeout(self::getConnectTimeout());

        return $options;
    }

    public static function getUserAgent(): string
    {
        return 'ai-provider-for-ppio/' . self::VERSION;
    }
}
