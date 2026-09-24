=== AI Provider for PPIO ===
Contributors:      bestony
Tags:              ai, connector, ppio, artificial-intelligence, vision
Requires at least: 7.0
Tested up to:      7.1
Stable tag:        1.0.0
Requires PHP:      7.4
License:           GPL-2.0-or-later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

PPIO provider for the WordPress PHP AI Client: text, vision, tools, structured output, and reasoning.

== Description ==

AI Provider for PPIO adds [PPIO](https://ppio.com/) as an OpenAI-compatible provider.

* Models are discovered from `GET /openai/v1/models`.
* Text and chat history use `POST /openai/v1/chat/completions`.
* Catalogued models support vision input, function calling, JSON Schema, and JSON Object output.
* `reasoning_content`, token usage, tool calls, and image content use the PHP AI Client parser.
* Unknown models stay visible as text-only models. Embedding, reranking, ASR, TTS, audio, and video
  models are visible without an unsupported chat capability.

Image generation, video or audio output, embeddings, and reranking are documented non-goals because
this provider implements only PPIO's model-list and chat-completion endpoints.

== Installation ==

1. Upload the plugin to `/wp-content/plugins/ai-provider-for-ppio/`.
2. Activate it in the Plugins screen.
3. Open Settings → Connectors, choose PPIO, and add an API key.

The API key is handled by the AI Client registry. This plugin does not read the Connectors option
directly.

== Configuration ==

Optional values can be set as environment variables or PHP constants:

* `PPIO_DEFAULT_MODEL` — preferred text and vision model. Default: `deepseek/deepseek-v4-flash`.
* `PPIO_BASE_URL` — API base URL. Default: `https://api.ppio.com/openai/v1`.
* `PPIO_STRUCTURED_OUTPUT` — `json_schema` (default), `json_object`, or `none`.
* `PPIO_MODEL_INPUT_MODALITIES` — include `image` in a comma-separated list to declare vision for all
  chat models, for example `text,image`.
* `PPIO_REQUEST_TIMEOUT` — request timeout in seconds. Default: `120`.
* `PPIO_CONNECT_TIMEOUT` — connection timeout in seconds. Default: `10`.
* `PPIO_SEPARATE_REASONING` — optional boolean override for `separate_reasoning`.
* `PPIO_ENABLE_THINKING` — optional boolean override for `enable_thinking`.

The automatic reasoning flags are enabled only for the documented models
`deepseek/deepseek-r1-turbo` and `deepseek/deepseek-v3.2-exp`. The structured JSON Schema request is
wrapped as `json_schema.name=ppio_response`; `none` removes `response_format` entirely.

== Privacy ==

Requests go to the configured PPIO base URL. Chat requests can contain prompts, history, tool
definitions, JSON schemas, and images supplied by the calling plugin. The AI Client sends the PPIO
API key as a Bearer credential. No request is sent until model discovery or generation is requested.

External service: [PPIO](https://ppio.com/), including its
[API key documentation](https://ppio.com/docs/support/api-key), [privacy policy](https://ppio.com/privacy),
and [terms](https://ppio.com/terms).

== Frequently Asked Questions ==

= Does this plugin need a separate settings page? =

No. The AI Client's Settings → Connectors screen owns the credential. Optional provider behavior uses
environment variables or PHP constants.

= Why is a newly listed model text-only? =

PPIO's model list does not publish capabilities. Unknown IDs remain usable for text while the static
catalog is updated for vision, tools, and structured output.

= Can this plugin generate images or embeddings? =

No. Those PPIO APIs are outside this provider's chat-completion scope.

== Changelog ==

= 1.0.0 =
* Initial release for PPIO's OpenAI-compatible model-list and chat-completion APIs.
* Added text, vision, function calling, structured output, reasoning flags, token usage, and request
  timeouts.
* Added a static capability catalog with safe text-only fallback for unknown models.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
