<?php

// phpcs:ignoreFile -- dev-only CLI harness; .gitattributes export-ignores it from releases.

/**
 * WordPress-free self-check for the PPIO provider.
 *
 * Run `php scripts/selfcheck.php` for catalog and configuration checks. Add `--sdk=/path/to/php-ai-client`
 * to exercise metadata parsing, request construction, response parsing and registry credential checks.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
if (!defined('ABSPATH')) {
    define('ABSPATH', $root . '/');
}
$GLOBALS['ppio_plugins_dir'] = dirname($root);
$GLOBALS['ppio_plugin_dir'] = $root;

require $root . '/src/autoload.php';

use Ppio\AiProvider\Util\PpioConfig;
use Ppio\AiProvider\Util\PpioModelCatalog;

$failures = 0;
$checks = 0;

function check(bool $condition, string $description): void
{
    global $failures, $checks;
    $checks++;
    if ($condition) {
        fwrite(STDOUT, "ok    {$description}\n");
    } else {
        $failures++;
        fwrite(STDERR, "FAIL  {$description}\n");
    }
}

// --- Static catalog ---------------------------------------------------------------------------
foreach (
    [
        'deepseek/deepseek-v4-flash-vision-exp',
        'qwen/qwen3.8-flash',
        'qwen/qwen3-vl-235b-a22b',
        'moonshotai/kimi-k3',
    ] as $modelId
) {
    check(PpioModelCatalog::supportsImageInput($modelId), "{$modelId} is vision capable");
}
check(!PpioModelCatalog::supportsImageInput('deepseek/deepseek-v4-flash'), 'the default model is text-only');
check(PpioModelCatalog::supportsFunctionCalling('deepseek/deepseek-v4-flash'), 'the default model supports tools');
check(PpioModelCatalog::supportsStructuredOutput('deepseek/deepseek-v4-flash'), 'the default model supports JSON schema');
check(!PpioModelCatalog::supportsFunctionCalling('vendor/future-chat'), 'unknown models do not gain tool support');
check(!PpioModelCatalog::supportsStructuredOutput('vendor/future-chat'), 'unknown models do not gain schema support');
foreach (['qwen/qwen3-embedding-8b', 'baai/bge-reranker-v2-m3', 'glm-asr-2512', 'fish/tts-1', 'vendor/video-v1'] as $modelId) {
    check(PpioModelCatalog::isUnsupportedModel($modelId), "{$modelId} is non-chat");
}
check(PpioModelCatalog::isEmbeddingModel('qwen/qwen3-embedding-8b'), 'embedding models are recognised');
check(PpioModelCatalog::shortId('deepseek/deepseek-v4-flash') === 'deepseek-v4-flash', 'vendor prefixes are stripped');
check(PpioModelCatalog::isPreviewOrFree('deepseek/deepseek-v3.2-exp'), 'experimental models are recognised');
check(PpioModelCatalog::isDatedSnapshot('deepseek/deepseek-v3-20260324'), 'dated model snapshots are recognised');
check(PpioModelCatalog::compareModelIds('deepseek/deepseek-v4-flash', 'vendor/future-chat') < 0, 'supported models sort before unknown models');
check(PpioModelCatalog::compareModelIds('deepseek/deepseek-v3', 'deepseek/deepseek-v3.2-exp') < 0, 'stable models sort before experimental models');

// --- Configuration -----------------------------------------------------------------------------
check(PpioConfig::getBaseUrl() === 'https://api.ppio.com/openai/v1', 'default base URL');
check(PpioConfig::getDefaultModelId() === 'deepseek/deepseek-v4-flash', 'default model');
check(PpioConfig::getStructuredOutputMode() === 'json_schema', 'structured output defaults to json_schema');
check(PpioConfig::getRequestTimeout() === 120.0, 'default request timeout');
check(PpioConfig::getConnectTimeout() === 10.0, 'default connect timeout');
check(PpioConfig::getUserAgent() === 'ai-provider-for-ppio/' . PpioConfig::VERSION, 'user agent includes the plugin version');
check(PpioConfig::getSeparateReasoning('deepseek/deepseek-r1-turbo') === true, 'R1 Turbo enables separate reasoning automatically');
check(PpioConfig::getSeparateReasoning('deepseek/deepseek-v4-flash') === null, 'other models omit separate reasoning automatically');
check(PpioConfig::getEnableThinking('deepseek/deepseek-v3.2-exp') === true, 'V3.2 experimental enables thinking automatically');
check(PpioConfig::getEnableThinking('deepseek/deepseek-v4-flash') === null, 'other models omit thinking automatically');

putenv('PPIO_BASE_URL=https://proxy.example.test/openai/v1/');
check(PpioConfig::getBaseUrl() === 'https://proxy.example.test/openai/v1', 'PPIO_BASE_URL is trimmed');
putenv('PPIO_BASE_URL');
putenv('PPIO_DEFAULT_MODEL=deepseek/deepseek-v3');
check(PpioConfig::getDefaultModelId() === 'deepseek/deepseek-v3', 'PPIO_DEFAULT_MODEL is honoured');
putenv('PPIO_DEFAULT_MODEL');
putenv('PPIO_STRUCTURED_OUTPUT=json_object');
check(PpioConfig::getStructuredOutputMode() === 'json_object', 'JSON object mode is honoured');
putenv('PPIO_STRUCTURED_OUTPUT');
putenv('PPIO_REQUEST_TIMEOUT=42');
putenv('PPIO_CONNECT_TIMEOUT=3');
check(PpioConfig::getRequestTimeout() === 42.0, 'request timeout override is honoured');
check(PpioConfig::getConnectTimeout() === 3.0, 'connect timeout override is honoured');
putenv('PPIO_REQUEST_TIMEOUT');
putenv('PPIO_CONNECT_TIMEOUT');
putenv('PPIO_MODEL_INPUT_MODALITIES=text,image');
check(PpioConfig::declaresImageInput(), 'the modality escape hatch declares image input');
putenv('PPIO_MODEL_INPUT_MODALITIES');
putenv('PPIO_SEPARATE_REASONING=false');
check(PpioConfig::getSeparateReasoning('deepseek/deepseek-r1-turbo') === false, 'reasoning override can disable the automatic flag');
putenv('PPIO_SEPARATE_REASONING');
putenv('PPIO_ENABLE_THINKING=true');
check(PpioConfig::getEnableThinking('deepseek/deepseek-v4-flash') === true, 'thinking override can enable the flag');
putenv('PPIO_ENABLE_THINKING');

// The plugin's autoloader and entry file both have direct-access guards.
define_ppio_wp_stubs();

$sdkPath = null;
foreach ($argv as $argument) {
    if (strpos($argument, '--sdk=') === 0) {
        $sdkPath = substr($argument, 6);
    }
}
$sdkLoaded = $sdkPath !== null && load_ppio_sdk($sdkPath);
if ($sdkLoaded) {
    use_ppio_sdk_checks();
} else {
    check(!PpioConfig::hasCredentials(), 'no credentials are reported without the AI Client SDK');
    fwrite(STDOUT, "skip  SDK-dependent checks (pass --sdk=<path to php-ai-client> to run them)\n");
}
use_ppio_plugin_checks($sdkLoaded);

function load_ppio_sdk(string $sdkPath): bool
{
    if (is_file($sdkPath . '/autoload.php')) {
        require $sdkPath . '/autoload.php';
        return true;
    }
    if (is_file($sdkPath . '/polyfills.php')) {
        require $sdkPath . '/polyfills.php';
        spl_autoload_register(static function (string $class) use ($sdkPath): void {
            $prefix = 'WordPress\\AiClient\\';
            if (strpos($class, $prefix) !== 0) {
                return;
            }
            $file = $sdkPath . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($file)) {
                require $file;
            }
        });
        return true;
    }
    return false;
}

function use_ppio_sdk_checks(): void
{
    $response = new \WordPress\AiClient\Providers\Http\DTO\Response(
        200,
        [],
        json_encode([
            'object' => 'list',
            'data' => [
                ['id' => 'deepseek/deepseek-v4-flash', 'title' => 'DeepSeek V4 Flash'],
                ['id' => 'deepseek/deepseek-v4-flash-vision-exp', 'title' => 'V4 Flash Vision'],
                ['id' => 'deepseek/deepseek-v3'],
                ['id' => 'qwen/qwen3.8-flash'],
                ['id' => 'qwen/qwen3-embedding-8b', 'title' => 'Qwen Embedding'],
                ['id' => 'baai/bge-reranker-v2-m3'],
                ['id' => 'fish/tts-1'],
                ['title' => 'malformed without id'],
                'malformed entry',
            ],
        ])
    );

    $parser = new class extends \Ppio\AiProvider\Metadata\PpioModelMetadataDirectory {
        public function parse(\WordPress\AiClient\Providers\Http\DTO\Response $response): array
        {
            return $this->parseResponseToModelMetadataList($response);
        }

        public function request(\WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum $method, string $path): \WordPress\AiClient\Providers\Http\DTO\Request
        {
            return $this->createRequest($method, $path);
        }
    };
    $discoveryRequest = $parser->request(\WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum::GET(), 'models');
    check($discoveryRequest->getUri() === 'https://api.ppio.com/openai/v1/models', 'model discovery uses the /models URL');
    check(($discoveryRequest->getHeaders()['User-Agent'][0] ?? null) === PpioConfig::getUserAgent(), 'model discovery identifies the plugin');
    $models = $parser->parse($response);
    check(count($models) === 7, 'model-list parsing preserves valid chat and non-chat entries');

    $byId = [];
    foreach ($models as $model) {
        $byId[$model->getId()] = $model;
    }
    check($models[0]->getId() === 'deepseek/deepseek-v4-flash', 'the configured flagship is sorted first');
    check($byId['deepseek/deepseek-v4-flash']->getName() === 'DeepSeek V4 Flash', 'model title is used as the display name');
    check($byId['deepseek/deepseek-v3']->getName() === 'deepseek/deepseek-v3', 'display name falls back to model ID');
    check($byId['qwen/qwen3-embedding-8b']->getSupportedCapabilities() === [], 'embedding entries have no capability');
    check(count($byId['deepseek/deepseek-v4-flash']->getSupportedCapabilities()) === 2, 'chat models declare text and history');

    $optionNames = static function ($model): array {
        return array_map(static fn($option): string => $option->getName()->value, $model->getSupportedOptions());
    };
    $optionValues = static function ($model, string $name) {
        foreach ($model->getSupportedOptions() as $option) {
            if ($option->getName()->value === $name) {
                return $option->getSupportedValues();
            }
        }
        return null;
    };
    check(count((array) $optionValues($byId['deepseek/deepseek-v4-flash-vision-exp'], 'inputModalities')) === 2, 'vision models declare text and image input');
    check(count((array) $optionValues($byId['deepseek/deepseek-v4-flash'], 'inputModalities')) === 1, 'text models declare text input only');
    check(in_array('functionDeclarations', $optionNames($byId['deepseek/deepseek-v4-flash']), true), 'allowlisted models declare function tools');
    check(in_array('outputSchema', $optionNames($byId['deepseek/deepseek-v4-flash']), true), 'allowlisted models declare structured output');
    check(!in_array('functionDeclarations', $optionNames($byId['qwen/qwen3.8-flash']), true) === false, 'current multimodal models retain tool support');
    putenv('PPIO_MODEL_INPUT_MODALITIES=text,image');
    $escapeModels = $parser->parse($response);
    putenv('PPIO_MODEL_INPUT_MODALITIES');
    check(count((array) $optionValues($escapeModels[0], 'inputModalities')) === 2, 'the modality escape hatch reaches metadata');

    $providerMetadata = \Ppio\AiProvider\Provider\PpioProvider::metadata();
    $transporter = new class implements \WordPress\AiClient\Providers\Http\Contracts\HttpTransporterInterface {
        public $request;
        public $body = [];
        public $queue = [];
        public function send(\WordPress\AiClient\Providers\Http\DTO\Request $request, ?\WordPress\AiClient\Providers\Http\DTO\RequestOptions $options = null): \WordPress\AiClient\Providers\Http\DTO\Response
        {
            $this->request = $request;
            $this->body = (array) $request->getData();
            return new \WordPress\AiClient\Providers\Http\DTO\Response(200, [], json_encode($this->queue));
        }
    };
    $bind = static function ($model, $transporter): void {
        $model->setHttpTransporter($transporter);
        $model->setRequestAuthentication(new \WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication('test-key'));
        $model->setRequestOptions(\Ppio\AiProvider\Util\PpioConfig::createRequestOptions());
    };
    $message = static fn(string $text) => new \WordPress\AiClient\Messages\DTO\Message(
        \WordPress\AiClient\Messages\Enums\MessageRoleEnum::user(),
        [new \WordPress\AiClient\Messages\DTO\MessagePart($text)]
    );

    $chat = new \Ppio\AiProvider\Models\PpioTextGenerationModel($byId['deepseek/deepseek-v4-flash'], $providerMetadata);
    $bind($chat, $transporter);
    $chat->setConfig(\WordPress\AiClient\Providers\Models\DTO\ModelConfig::fromArray([
        'systemInstruction' => 'Be concise.',
        'maxTokens' => 256,
        'candidateCount' => 1,
        'temperature' => 0.2,
        'topP' => 0.8,
        'topK' => 40,
        'presencePenalty' => 0.1,
        'frequencyPenalty' => 0.1,
        'outputMimeType' => 'application/json',
        'outputSchema' => ['type' => 'object', 'properties' => ['ok' => ['type' => 'boolean']]],
        'functionDeclarations' => [[
            'name' => 'lookup',
            'description' => 'Look up a value.',
            'parameters' => ['type' => 'object', 'properties' => ['key' => ['type' => 'string']]],
        ]],
    ]));
    $transporter->queue = [
        'id' => 'chat-1',
        'choices' => [[
            'message' => ['role' => 'assistant', 'reasoning_content' => 'pondering', 'content' => 'Answer'],
            'finish_reason' => 'stop',
        ]],
        'usage' => ['prompt_tokens' => 7, 'completion_tokens' => 3, 'total_tokens' => 10],
    ];
    $result = $chat->generateTextResult([$message('hello')]);
    check($transporter->request->getUri() === 'https://api.ppio.com/openai/v1/chat/completions', 'chat requests target PPIO chat completions');
    check(($transporter->body['model'] ?? null) === 'deepseek/deepseek-v4-flash', 'chat requests carry the model ID');
    check(($transporter->body['max_tokens'] ?? null) === 256, 'max tokens are forwarded');
    check(($transporter->body['top_k'] ?? null) === 40, 'topK is sent as top_k');
    check(($transporter->body['tools'][0]['function']['name'] ?? null) === 'lookup', 'function declarations become tools');
    check(($transporter->body['response_format']['json_schema']['name'] ?? null) === 'ppio_response', 'JSON schema uses a named wrapper');
    check(($transporter->request->getHeaders()['Authorization'][0] ?? null) === 'Bearer test-key', 'requests use Bearer authentication');
    check($transporter->request->getOptions()->getTimeout() === 120.0, 'generation requests use the configured timeout');
    check($result->getTokenUsage()->getTotalTokens() === 10, 'token usage is parsed');
    $channels = [];
    foreach ($result->getCandidates()[0]->getMessage()->getParts() as $part) {
        if ($part->getType()->isText()) {
            $channels[$part->getChannel()->value] = $part->getText();
        }
    }
    check(($channels['thought'] ?? null) === 'pondering', 'reasoning_content becomes a thought part');
    check(($channels['content'] ?? null) === 'Answer', 'answer content remains separate');

    putenv('PPIO_STRUCTURED_OUTPUT=json_object');
    $chat->generateTextResult([$message('hello')]);
    check(($transporter->body['response_format']['type'] ?? null) === 'json_object', 'JSON object mode is sent');
    putenv('PPIO_STRUCTURED_OUTPUT=none');
    $chat->generateTextResult([$message('hello')]);
    check(!isset($transporter->body['response_format']), 'none mode omits response_format');
    putenv('PPIO_STRUCTURED_OUTPUT');

    $vision = new \Ppio\AiProvider\Models\PpioTextGenerationModel($byId['deepseek/deepseek-v4-flash-vision-exp'], $providerMetadata);
    $bind($vision, $transporter);
    $vision->setConfig(\WordPress\AiClient\Providers\Models\DTO\ModelConfig::fromArray([]));
    $vision->generateTextResult([new \WordPress\AiClient\Messages\DTO\Message(
        \WordPress\AiClient\Messages\Enums\MessageRoleEnum::user(),
        [
            new \WordPress\AiClient\Messages\DTO\MessagePart('Describe this.'),
            new \WordPress\AiClient\Messages\DTO\MessagePart(new \WordPress\AiClient\Files\DTO\File('data:image/png;base64,iVBORw0KGgo=', 'image/png')),
        ]
    )]);
    $contentTypes = array_column($transporter->body['messages'][0]['content'], 'type');
    check(in_array('image_url', $contentTypes, true), 'vision requests use image_url content parts');

    $reasoning = new \Ppio\AiProvider\Models\PpioTextGenerationModel($byId['deepseek/deepseek-v3'], $providerMetadata);
    $bind($reasoning, $transporter);
    $reasoning->setConfig(\WordPress\AiClient\Providers\Models\DTO\ModelConfig::fromArray([]));
    putenv('PPIO_SEPARATE_REASONING=true');
    $reasoning->generateTextResult([$message('hello')]);
    check(($transporter->body['separate_reasoning'] ?? null) === true, 'separate reasoning override is sent');
    putenv('PPIO_SEPARATE_REASONING');

    check(\Ppio\AiProvider\Provider\PpioProvider::url('/models') === 'https://api.ppio.com/openai/v1/models', 'model discovery targets /models');
    check(\Ppio\AiProvider\Provider\PpioProvider::url('chat/completions') === 'https://api.ppio.com/openai/v1/chat/completions', 'provider URL joining is stable');
}

function use_ppio_plugin_checks(bool $hasSdk): void
{
    require dirname(__DIR__) . '/plugin.php';
    $callbacks = $GLOBALS['ppio_actions']['init'][5] ?? [];
    check($callbacks !== [], 'provider registration is attached at init priority 5');
    check(($GLOBALS['ppio_filters']['wpai_preferred_text_models'] ?? []) !== [], 'text preference filter is registered');
    check(($GLOBALS['ppio_filters']['wpai_preferred_vision_models'] ?? []) !== [], 'vision preference filter is registered');
    $apply = static function (string $hook, $value) {
        foreach (($GLOBALS['ppio_filters'][$hook] ?? []) as $callbacks) {
            foreach ($callbacks as $callback) {
                $value = $callback($value);
            }
        }
        return $value;
    };
    $untouched = [['openai', 'gpt-5']];
    check($apply('wpai_preferred_text_models', $untouched) === $untouched, 'preference filters do nothing without credentials');
    if (!$hasSdk) {
        return;
    }
    \WordPress\AiClient\AiClient::defaultRegistry()->setHttpTransporter(new class implements \WordPress\AiClient\Providers\Http\Contracts\HttpTransporterInterface {
        public function send(\WordPress\AiClient\Providers\Http\DTO\Request $request, ?\WordPress\AiClient\Providers\Http\DTO\RequestOptions $options = null): \WordPress\AiClient\Providers\Http\DTO\Response
        {
            return new \WordPress\AiClient\Providers\Http\DTO\Response(200, [], '{}');
        }
    });
    check(!PpioConfig::hasCredentials(), 'registry has no credentials before registration');
    foreach ($callbacks as $callback) {
        $callback();
    }
    check(\WordPress\AiClient\AiClient::defaultRegistry()->hasProvider(PpioConfig::PROVIDER_ID), 'provider is registered in the AI Client registry');
    check(!PpioConfig::hasCredentials(), 'registration alone does not count as credentials');
    \WordPress\AiClient\AiClient::defaultRegistry()->setProviderRequestAuthentication(
        PpioConfig::PROVIDER_ID,
        new \WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication('test-key')
    );
    check(PpioConfig::hasCredentials(), 'registry authentication is detected');
    check($apply('wpai_preferred_text_models', $untouched) === [['ppio', 'deepseek/deepseek-v4-flash'], $untouched[0]], 'the configured model is preferred and existing entries survive');
    check($apply('wpai_preferred_vision_models', []) === [['ppio', 'deepseek/deepseek-v4-flash']], 'the vision filter uses the configured chat model');
}

function define_ppio_wp_stubs(): void
{
    if (function_exists('add_action')) {
        return;
    }
    function add_action(string $hook, $callback, int $priority = 10): void
    {
        $GLOBALS['ppio_actions'][$hook][$priority][] = $callback;
    }
    function add_filter(string $hook, $callback, int $priority = 10): void
    {
        $GLOBALS['ppio_filters'][$hook][$priority][] = $callback;
    }
    function __(string $text, ?string $domain = null): string
    {
        return $text;
    }
    function esc_html(string $text): string
    {
        return $text;
    }
    function esc_html__(string $text, ?string $domain = null): string
    {
        return $text;
    }
    function plugin_basename(string $file): string
    {
        $dir = $GLOBALS['ppio_plugins_dir'] ?? '';
        return is_string($dir) && strpos($file, $dir) === 0 ? ltrim(substr($file, strlen($dir)), '/') : basename($file);
    }
    function load_plugin_textdomain(string $domain, bool $deprecated = false, ?string $path = null): bool
    {
        return true;
    }
}

fwrite(STDOUT, sprintf("\n%d checks, %d failure(s)\n", $checks, $failures));
exit($failures === 0 ? 0 : 1);
