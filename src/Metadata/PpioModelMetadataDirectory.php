<?php

/**
 * PPIO model metadata directory.
 *
 * @package Ppio\AiProvider
 */

declare(strict_types=1);

namespace Ppio\AiProvider\Metadata;

use WordPress\AiClient\Messages\Enums\ModalityEnum;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\Http\Exception\ResponseException;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\Models\DTO\SupportedOption;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;
use WordPress\AiClient\Providers\Models\Enums\OptionEnum;
use WordPress\AiClient\Providers\OpenAiCompatibleImplementation\AbstractOpenAiCompatibleModelMetadataDirectory;
use Ppio\AiProvider\Provider\PpioProvider;
use Ppio\AiProvider\Util\PpioConfig;
use Ppio\AiProvider\Util\PpioModelCatalog;

/**
 * Converts PPIO's identity-only model list into the capability metadata used by the AI Client.
 *
 * @phpstan-type ModelsResponseData array{data: list<array<string, mixed>>}
 */
class PpioModelMetadataDirectory extends AbstractOpenAiCompatibleModelMetadataDirectory
{
    protected function createRequest(HttpMethodEnum $method, string $path, array $headers = [], $data = null): Request
    {
        $headers['User-Agent'] = PpioConfig::getUserAgent();

        return new Request(
            $method,
            PpioProvider::url($path),
            $headers,
            $data,
            PpioConfig::createRequestOptions()
        );
    }

    /**
     * @return list<ModelMetadata>
     */
    protected function parseResponseToModelMetadataList(Response $response): array
    {
        /** @var ModelsResponseData $responseData */
        $responseData = $response->getData();
        if (!isset($responseData['data']) || !is_array($responseData['data']) || $responseData['data'] === []) {
            throw ResponseException::fromMissingData('PPIO', 'data');
        }

        $models = [];
        foreach ($responseData['data'] as $modelData) {
            if (!is_array($modelData)) {
                continue;
            }

            $modelId = $modelData['id'] ?? null;
            if (!is_string($modelId) || $modelId === '') {
                continue;
            }

            $displayName = isset($modelData['title']) && is_string($modelData['title']) && $modelData['title'] !== ''
                ? $modelData['title']
                : $modelId;

            if (!PpioModelCatalog::isTextModel($modelId)) {
                // Keep non-chat models visible, but do not let the SDK select them for chat requests.
                $models[] = new ModelMetadata($modelId, $displayName, [], []);
                continue;
            }

            $models[] = new ModelMetadata(
                $modelId,
                $displayName,
                [CapabilityEnum::textGeneration(), CapabilityEnum::chatHistory()],
                $this->createTextOptions($modelId)
            );
        }

        $preferredModelId = PpioConfig::getDefaultModelId();
        usort(
            $models,
            static function (ModelMetadata $a, ModelMetadata $b) use ($preferredModelId): int {
                if ($preferredModelId !== '') {
                    $aPreferred = $a->getId() === $preferredModelId ? 0 : 1;
                    $bPreferred = $b->getId() === $preferredModelId ? 0 : 1;
                    if ($aPreferred !== $bPreferred) {
                        return $aPreferred <=> $bPreferred;
                    }
                }

                return PpioModelCatalog::compareModelIds($a->getId(), $b->getId());
            }
        );

        return $models;
    }

    /**
     * @return list<SupportedOption>
     */
    private function createTextOptions(string $modelId): array
    {
        $inputModalities = [[ModalityEnum::text()]];
        if (PpioModelCatalog::supportsImageInput($modelId) || PpioConfig::declaresImageInput()) {
            $inputModalities[] = [ModalityEnum::text(), ModalityEnum::image()];
        }

        $supportsStructuredOutput = PpioModelCatalog::supportsStructuredOutput($modelId);
        $options = [
            new SupportedOption(OptionEnum::systemInstruction()),
            new SupportedOption(OptionEnum::maxTokens()),
            new SupportedOption(OptionEnum::stopSequences()),
            new SupportedOption(OptionEnum::customOptions()),
            new SupportedOption(OptionEnum::inputModalities(), $inputModalities),
            new SupportedOption(OptionEnum::outputModalities(), [[ModalityEnum::text()]]),
            new SupportedOption(
                OptionEnum::outputMimeType(),
                $supportsStructuredOutput ? ['text/plain', 'application/json'] : ['text/plain']
            ),
            new SupportedOption(OptionEnum::candidateCount()),
            new SupportedOption(OptionEnum::temperature()),
            new SupportedOption(OptionEnum::topP()),
            new SupportedOption(OptionEnum::topK()),
            new SupportedOption(OptionEnum::presencePenalty()),
            new SupportedOption(OptionEnum::frequencyPenalty()),
            new SupportedOption(OptionEnum::logprobs()),
            new SupportedOption(OptionEnum::topLogprobs()),
        ];

        if (PpioModelCatalog::supportsFunctionCalling($modelId)) {
            $options[] = new SupportedOption(OptionEnum::functionDeclarations());
        }

        if ($supportsStructuredOutput) {
            $options[] = new SupportedOption(OptionEnum::outputSchema());
        }

        return $options;
    }
}
