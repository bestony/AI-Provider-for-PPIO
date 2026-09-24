<?php

/**
 * Ppio text generation model class file.
 *
 * @package Ppio\AiProvider
 */

declare(strict_types=1);

namespace Ppio\AiProvider\Models;

use WordPress\AiClient\Providers\OpenAiCompatibleImplementation\AbstractOpenAiCompatibleTextGenerationModel;
use Ppio\AiProvider\Util\PpioConfig;

/**
 * Text generation model for Ppio chat models.
 *
 * Everything below the request body — message mapping, vision input, tool calls, response parsing,
 * reasoning content, token usage — is handled by the SDK base class. Only Ppio's request shape
 * quirks need overriding.
 */
class PpioTextGenerationModel extends AbstractOpenAiCompatibleTextGenerationModel
{
    use PpioRequestTrait;

    /**
     * {@inheritDoc}
     *
     * @param list<\WordPress\AiClient\Messages\DTO\Message> $prompt The prompt to generate text for.
     * @return array<string, mixed> The parameters for the API request.
     */
    protected function prepareGenerateTextParams(array $prompt): array
    {
        $params = parent::prepareGenerateTextParams($prompt);

        /*
         * An empty array signals "send no response_format" (see prepareResponseFormatParam()); leaving
         * the key in place would send `"response_format": []`, which is a 400.
         */
        if (isset($params['response_format']) && $params['response_format'] === []) {
            unset($params['response_format']);
        }

        // PPIO documents the OpenAI-compatible top_k field. Older SDK releases do not map topK yet.
        $topK = $this->getConfig()->getTopK();
        if ($topK !== null && !isset($params['top_k'])) {
            $params['top_k'] = $topK;
        }

        $modelId = $this->metadata()->getId();
        $separateReasoning = PpioConfig::getSeparateReasoning($modelId);
        if ($separateReasoning !== null) {
            $params['separate_reasoning'] = $separateReasoning;
        }

        $enableThinking = PpioConfig::getEnableThinking($modelId);
        if ($enableThinking !== null) {
            $params['enable_thinking'] = $enableThinking;
        }

        return $params;
    }

    /**
     * {@inheritDoc}
     *
     * Fixes the request shape for structured output, which the SDK base class gets wrong.
     *
     * The base class sends `{"type":"json_schema","json_schema":<schema>}`, but the OpenAI shape
     * Ppio follows wraps the schema in a named object:
     * `{"type":"json_schema","json_schema":{"name":...,"schema":{...}}}`. Without the wrapper the
     * request is rejected with `400 ... response_format` — which is what the AI plugin's JSON
     * features (Editorial Notes, Slug Generation, …) were hitting.
     *
     * @param array<string, mixed>|null $outputSchema The output schema, or null for plain JSON mode.
     * @return array<string, mixed> The response format parameter, or an empty array to send none.
     */
    protected function prepareResponseFormatParam(?array $outputSchema): array
    {
        $mode = PpioConfig::getStructuredOutputMode();

        if ($mode === 'none') {
            return [];
        }

        if ($mode === 'json_object' || !is_array($outputSchema)) {
            return ['type' => 'json_object'];
        }

        return [
            'type' => 'json_schema',
            'json_schema' => [
                'name' => 'ppio_response',
                'schema' => $outputSchema,
            ],
        ];
    }
}
