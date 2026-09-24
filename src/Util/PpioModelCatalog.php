<?php

/**
 * Static PPIO capability catalog.
 *
 * PPIO's OpenAI-compatible model list contains identity and pricing fields, but no capability
 * metadata. Unknown IDs remain usable for plain text only; optional capabilities are allowlisted.
 *
 * @package Ppio\AiProvider
 */

declare(strict_types=1);

namespace Ppio\AiProvider\Util;

final class PpioModelCatalog
{
    /** @var list<string> */
    private const VISION_PATTERNS = [
        '#^deepseek/deepseek-v4\.1-flash(?:$|-|_)#i',
        '#^deepseek/deepseek-v4(?:\.1)?-flash-vision(?:$|-|_)#i',
        '#^qwen/qwen(?:2\.5|3(?:\.5|\.6|\.8)?)-vl(?:$|-|_)#i',
        '#^qwen/qwen3(?:\.5|\.6|\.8)(?:$|-|_)#i',
        '#^qwen/qwen3-omni(?:$|-|_)#i',
        '#^moonshotai/kimi-k(?:2\.5|2\.6|2\.7-code|3)(?:$|-|_)#i',
        '#^minimax/minimax-m3(?:$|-|_)#i',
        '#^xiaomimimo/mimo-v2\.5(?:$|-|_)#i',
        '#^zai-org/glm-5\.3-flash(?:$|-|_)#i',
        '#^zai-org/glm-4\.[56]v(?:$|-|_)#i',
        '#(^|[/:_-])(vision|visual|multimodal|omni|vl|qvq|llava|pixtral)([/:_-]|$)#i',
    ];

    /** @var list<string> */
    private const FUNCTION_CALLING_PATTERNS = [
        '#^deepseek/deepseek-(?:v3|v4|r1)(?:$|[-_./])#i',
        '#^qwen/qwen(?:-2\.5|2\.5|3)(?:$|[-_./])#i',
        '#^zai-org/glm-[45](?:\.[0-9]+)?v?(?:$|-|_)#i',
        '#^moonshotai/kimi-k(?:2|3)(?:$|-|_)#i',
        '#^minimax(?:ai)?/minimax-m(?:2|3)(?:$|-|_)#i',
        '#^xiaomimimo/mimo-v2\.5(?:$|-|_)#i',
        '#^baidu/ernie-4\.5(?:$|-|_)#i',
    ];

    /** @var list<string> */
    private const STRUCTURED_OUTPUT_PATTERNS = [
        '#^deepseek/deepseek-(?:v3|v4|r1)(?:$|[-_./])#i',
        '#^qwen/qwen(?:-2\.5|2\.5|3)(?:$|[-_./])#i',
        '#^zai-org/glm-(?:4\.[67]|5)(?:\.[0-9]+)?v?(?:$|-|_)#i',
        '#^moonshotai/kimi-k(?:2\.5|2\.6|2\.7-code|3)(?:$|-|_)#i',
        '#^minimax(?:ai)?/minimax-m(?:2|3)(?:$|-|_)#i',
        '#^xiaomimimo/mimo-v2\.5(?:$|-|_)#i',
    ];

    /** @var list<string> */
    private const NON_CHAT_PATTERNS = [
        '#(^|[/:_-])(embed|embedding|embeddings|rerank|reranker|moderation|asr|tts|stt|speech|transcribe|audio|video)([/:_-]|$)#i',
    ];

    public static function supportsImageInput(string $modelId): bool
    {
        foreach (self::VISION_PATTERNS as $pattern) {
            if (preg_match($pattern, $modelId) === 1) {
                return true;
            }
        }

        return false;
    }

    public static function isVisionModel(string $modelId): bool
    {
        return self::supportsImageInput($modelId);
    }

    public static function supportsFunctionCalling(string $modelId): bool
    {
        foreach (self::FUNCTION_CALLING_PATTERNS as $pattern) {
            if (preg_match($pattern, $modelId) === 1) {
                return true;
            }
        }

        return false;
    }

    public static function isFunctionCallingModel(string $modelId): bool
    {
        return self::supportsFunctionCalling($modelId);
    }

    public static function supportsStructuredOutput(string $modelId): bool
    {
        foreach (self::STRUCTURED_OUTPUT_PATTERNS as $pattern) {
            if (preg_match($pattern, $modelId) === 1) {
                return true;
            }
        }

        return false;
    }

    public static function isStructuredOutputModel(string $modelId): bool
    {
        return self::supportsStructuredOutput($modelId);
    }

    public static function isEmbeddingModel(string $modelId): bool
    {
        return preg_match('#(^|[/:_-])(embed|embedding|embeddings)([/:_-]|$)#i', $modelId) === 1;
    }

    public static function isTextModel(string $modelId): bool
    {
        foreach (self::NON_CHAT_PATTERNS as $pattern) {
            if (preg_match($pattern, $modelId) === 1) {
                return false;
            }
        }

        return true;
    }

    public static function isUnsupportedModel(string $modelId): bool
    {
        return !self::isTextModel($modelId);
    }

    public static function rejectsSamplingParameters(string $modelId): bool
    {
        return false;
    }

    public static function compareModelIds(string $modelIdA, string $modelIdB): int
    {
        foreach (['isUnsupportedModel', 'isPreviewOrFree', 'isDatedSnapshot'] as $criterion) {
            $delta = (int) self::$criterion($modelIdA) <=> (int) self::$criterion($modelIdB);
            if ($delta !== 0) {
                return $delta;
            }
        }

        return strnatcasecmp($modelIdA, $modelIdB);
    }

    public static function isDatedSnapshot(string $modelId): bool
    {
        return preg_match('/-\d{8}$|-\d{4}-\d{2}-\d{2}$/', $modelId) === 1;
    }

    public static function isPreviewOrFree(string $modelId): bool
    {
        return strpos(self::shortId($modelId), ':free') !== false
            || stripos($modelId, 'preview') !== false
            || stripos($modelId, '-exp') !== false
            || stripos($modelId, 'beta') !== false;
    }

    public static function shortId(string $modelId): string
    {
        $slashPosition = strrpos($modelId, '/');

        return $slashPosition === false ? $modelId : substr($modelId, $slashPosition + 1);
    }
}
