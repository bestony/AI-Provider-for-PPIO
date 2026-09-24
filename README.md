# AI Provider for PPIO

An OpenAI-compatible [PPIO](https://ppio.com/) provider for the [WordPress PHP AI Client](https://github.com/WordPress/php-ai-client).
It supports PPIO chat completions for text, vision, tools, structured output, and reasoning output.

## Requirements

- PHP 7.4 or later.
- WordPress 7.0 or later with the `ai` plugin or another PHP AI Client runtime.
- A PPIO API key.

## Installation

1. Copy this directory to `/wp-content/plugins/ai-provider-for-ppio/`.
2. Activate **AI Provider for PPIO**.
3. Add the key in **Settings → Connectors** for PPIO. The provider uses the AI Client registry and
   does not read the Connectors option directly.

The provider registers on `init` priority 5 and prefers `deepseek/deepseek-v4-flash` for text and
vision features when a credential is available.

```php
use WordPress\AiClient\AiClient;

$result = AiClient::prompt('Summarise this post in one sentence.')
    ->usingProvider('ppio')
    ->usingModel('deepseek/deepseek-v4-flash')
    ->generateTextResult();

echo $result->toText();
```

## Supported capabilities

PPIO exposes `GET /openai/v1/models` and `POST /openai/v1/chat/completions`. The model list has no
capability fields, so this plugin keeps a reviewed catalog in `src/Util/PpioModelCatalog.php`.

- Text generation and chat history.
- Vision input for catalogued multimodal models, plus the `PPIO_MODEL_INPUT_MODALITIES` escape hatch.
- Function calling for catalogued PPIO models.
- JSON Schema and JSON Object output for catalogued models.
- PPIO `reasoning_content`, token usage, tool calls, and image content through the SDK parser.

Unknown model IDs remain visible and are text-only. Embeddings, rerankers, ASR, TTS, audio, video, and
other non-chat models remain visible with no chat capability.

Image generation, video or audio output, embeddings, and reranking are outside this provider. PPIO
offers separate endpoints for those products, while this plugin implements only the model-list and
chat-completion endpoints requested here.

## Configuration

All optional settings are environment variables or PHP constants. The API key remains managed by the
AI Client registry.

| Setting | Default | Purpose |
| --- | --- | --- |
| `PPIO_DEFAULT_MODEL` | `deepseek/deepseek-v4-flash` | Model placed first in text and vision preference lists |
| `PPIO_BASE_URL` | `https://api.ppio.com/openai/v1` | OpenAI-compatible API base URL |
| `PPIO_STRUCTURED_OUTPUT` | `json_schema` | `json_schema`, `json_object`, or `none` |
| `PPIO_MODEL_INPUT_MODALITIES` | unset | Include `image` (for example `text,image`) to declare vision for every chat model |
| `PPIO_REQUEST_TIMEOUT` | `120` | Request timeout in seconds |
| `PPIO_CONNECT_TIMEOUT` | `10` | Connection timeout in seconds |
| `PPIO_SEPARATE_REASONING` | automatic | Override PPIO's `separate_reasoning` flag |
| `PPIO_ENABLE_THINKING` | automatic | Override PPIO's `enable_thinking` flag |

Automatic reasoning flags follow PPIO's documentation: `deepseek/deepseek-r1-turbo` receives
`separate_reasoning=true`, and `deepseek/deepseek-v3.2-exp` receives `enable_thinking=true`.

Structured output uses the named PPIO shape:

```json
{"type":"json_schema","json_schema":{"name":"ppio_response","schema":{}}}
```

`json_object` sends only `{ "type": "json_object" }`. `none` omits `response_format`.

## Privacy and external services

The plugin sends the model list request and chat-completion requests to the configured PPIO base URL.
Chat requests can contain prompts, conversation history, tool definitions, JSON schemas, and attached
images supplied by the calling plugin. The API key is sent as a Bearer credential by the AI Client.
No request is made until the AI Client refreshes models or a caller requests a generation.

See PPIO's [API key documentation](https://ppio.com/docs/support/api-key),
[privacy policy](https://ppio.com/privacy), and [terms](https://ppio.com/terms).

## Development

```bash
php scripts/selfcheck.php
php -l plugin.php
find src -name '*.php' -print0 | xargs -0 -n1 php -l
```

Pass `--sdk=/path/to/php-ai-client` to the self-check to exercise request and response DTOs without a
network call or a real key.

## License

GPL-2.0-or-later
