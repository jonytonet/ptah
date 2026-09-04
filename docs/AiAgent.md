# AI Agent Module

> Floating conversational AI chat widget for Ptah Forge — powered by [prism-php/prism](https://github.com/prism-php/prism).

---

## Table of Contents

- [Overview](#overview)
- [Prerequisites](#prerequisites)
- [Installation](#installation)
- [Providers](#providers)
  - [OpenAI-compatible platforms (`openai_compatible`)](#openai-compatible-platforms-openai_compatible)
- [Configuration Reference](#configuration-reference)
- [Admin Screen — Configuring a Provider](#admin-screen--configuring-a-provider)
- [Customising the System Prompt](#customising-the-system-prompt)
- [Custom Tools (Function Calling)](#custom-tools-function-calling)
- [Rate Limiting](#rate-limiting)
- [Conversation History](#conversation-history)
- [Extending with Custom Providers](#extending-with-custom-providers)
- [Security Considerations](#security-considerations)
- [Troubleshooting](#troubleshooting)

---

## Overview

The **AI Agent** module adds a floating chat widget to every page of the Ptah Forge dashboard layout — similar to JivoChat or Intercom, but talking to your own AI backend. End-users click the bubble in the bottom-right corner to open a conversation panel, type messages, and receive responses from the configured AI provider.

Key features:

| Feature | Details |
|---|---|
| **Floating widget** | Fixed bottom-right bubble — always visible, never intrusive |
| **Provider abstraction** | Supports OpenAI, Anthropic, Gemini, Ollama via `prism-php/prism` |
| **Admin config screen** | CRUD UI at `/ptah-ai/models` — no code changes needed to switch providers |
| **Encrypted API keys** | Keys are stored using Laravel's `encrypted` cast (uses `APP_KEY`) |
| **Function calling / tools** | Register custom tools for the AI to call |
| **Conversation history** | Persisted per user (authenticated) or per session (guests) in `ptah_ai_conversations` — with in-widget history panel |
| **Rate limiting** | Configurable per-session request limit (default: 30/min) |
| **Dark mode** | Fully compatible with Ptah's `.ptah-dark` class-based dark mode |
| **i18n** | EN + PT-BR translations included |

---

## Prerequisites

- Ptah `^1.0`
- PHP `^8.2`
- Laravel `^11 | ^12`
- Livewire `^4.0`
- [prism-php/prism](https://github.com/prism-php/prism) installed separately (see below)

---

## Installation

### 1. Install the prism-php/prism package

```bash
composer require prism-php/prism
```

### 2. Enable the module

```bash
php artisan ptah:module ai_agent
```

This command will:
1. Run `composer require prism-php/prism` (if not already installed)
2. Publish the two module migrations to `database/migrations/`
3. Run `php artisan migrate`
4. Set `PTAH_MODULE_AI_AGENT=true` in `.env`

### 3. Configure a provider

Visit `/ptah-ai/models` in your app and create a new configuration (see [Admin Screen](#admin-screen--configuring-a-provider)).

That's it — the chat widget appears automatically for authenticated users.

---

## Providers

| Provider | `provider` value | Streaming | Requires |
|---|---|---|---|
| **OpenAI** | `openai` | yes | API key from [platform.openai.com](https://platform.openai.com/api-keys) |
| **Anthropic** | `anthropic` | yes | API key from [console.anthropic.com](https://console.anthropic.com/account/keys) |
| **Google Gemini** | `gemini` | yes | API key from [aistudio.google.com](https://aistudio.google.com/app/apikey) |
| **xAI (Grok)** | `xai` | yes | API key from [console.x.ai](https://console.x.ai) |
| **DeepSeek** | `deepseek` | yes | API key from [platform.deepseek.com](https://platform.deepseek.com) |
| **Groq** | `groq` | yes | API key from [console.groq.com/keys](https://console.groq.com/keys) — free tier available |
| **Mistral** | `mistral` | yes | API key from [console.mistral.ai/api-keys](https://console.mistral.ai/api-keys) |
| **OpenRouter** | `openrouter` | yes | API key from [openrouter.ai/keys](https://openrouter.ai/keys) |
| **Perplexity** | `perplexity` | yes | API key from [perplexity.ai](https://www.perplexity.ai/settings/api) |
| **z.ai (GLM)** | `z` | **no** | API key from [z.ai](https://z.ai) |
| **Ollama** | `ollama` | yes | Local Ollama running at `http://localhost:11434` — no API key needed |
| **OpenAI-compatible** | `openai_compatible` | yes | An **API Endpoint is required** — see below |

**Streaming** is a capability of the provider, not a setting. `PTAH_AI_STREAM`
defaults to `true`, but the widget checks whether the resolved provider can
actually stream and silently uses the non-streaming path when it cannot. Today
`z` is the only provider in that position; you do not need to configure anything
for it, and the answer simply arrives at once instead of token by token.

### OpenAI-compatible platforms (`openai_compatible`)

Many services and self-hosted servers speak the OpenAI **chat completions** API
without being OpenAI. They do **not** work under the `openai` provider, because
that one targets OpenAI's newer *Responses* API (`/v1/responses`), which they do
not implement — the request fails with an unexplained 400/422.

Use `openai_compatible` for all of these, with the **API Endpoint** pointing at
the server's OpenAI-compatible base URL:

| Platform | Endpoint |
|---|---|
| Together AI | `https://api.together.xyz/v1` |
| Fireworks AI | `https://api.fireworks.ai/inference/v1` |
| Cerebras | `https://api.cerebras.ai/v1` |
| SambaNova | `https://api.sambanova.ai/v1` |
| vLLM (self-hosted) | `http://your-server:8000/v1` |
| LM Studio | `http://localhost:1234/v1` |
| llama.cpp server | `http://localhost:8080/v1` |
| LocalAI | `http://localhost:8080/v1` |

The endpoint is mandatory for this option: there is no sensible default for
somebody else's server, and the admin screen rejects the form without it.

> **Azure OpenAI is NOT covered by this option.** It authenticates with an
> `api-key` header (or an Entra ID token) rather than
> `Authorization: Bearer`, and requires an `api-version` query parameter — so
> pointing `openai_compatible` at an Azure resource fails on authentication.
> Everything in the table above uses Bearer auth, which is why it works.
> Azure needs a dedicated provider; if you need it, open an issue.

> **How it works, and its one rough edge.** `openai_compatible` is a ptah alias,
> not a Prism provider: it is resolved onto a Prism provider whose handler posts
> to plain `chat/completions` with the vanilla payload (`model`, `messages`,
> `max_tokens`, `temperature`, `top_p`, `tools`, `tool_choice`). For the duration
> of a request it borrows that provider's configuration slot, restored
> afterwards. This works and is tested, but it is a workaround for Prism having
> no generic OpenAI-compatible provider; if that lands upstream, this alias goes
> away and existing configs will need their `provider` value updated.

---

## Configuration Reference

All settings live in `config/ptah.php` under the `ai_agent` key:

```php
'ai_agent' => [
    // Default system prompt injected into every conversation
    'system_prompt' => env('PTAH_AI_SYSTEM_PROMPT', 'You are a helpful assistant.'),

    // Max number of messages kept per session (older messages are dropped)
    'max_history'   => (int) env('PTAH_AI_MAX_HISTORY', 20),

    // Max requests per minute per session
    'rate_limit'    => (int) env('PTAH_AI_RATE_LIMIT', 30),

    // Rewrites `"properties": []` to `{}` in outgoing tool payloads. A tool
    // with no arguments serialises its empty parameter list as a JSON array,
    // which is invalid JSON Schema; strict providers (x.ai, OpenAI's structured
    // mode, most self-hosted OpenAI-compatible servers) reject the whole
    // request for it — and ptah's own built-in tools take no arguments.
    // Leave it on: the rewrite is a correctness fix, so a lenient provider sees
    // no difference.
    'normalize_tool_schema' => (bool) env('PTAH_AI_NORMALIZE_TOOL_SCHEMA', true),

    // Custom tools (instances of AiToolInterface)
    'tools'         => [],
],
```

### Available `.env` variables

| Variable | Default | Description |
|---|---|---|
| `PTAH_MODULE_AI_AGENT` | `false` | Enable / disable the module |
| `PTAH_AI_SYSTEM_PROMPT` | `You are a helpful assistant.` | Default system prompt |
| `PTAH_AI_MAX_HISTORY` | `20` | Max messages kept in session history |
| `PTAH_AI_RATE_LIMIT` | `30` | Max requests per minute per session |
| `PTAH_AI_STREAM` | `true` | Stream the answer token by token **when the provider supports it** (see the note under Providers) |
| `PTAH_AI_NORMALIZE_TOOL_SCHEMA` | `true` | Fix `"properties": []` in tool payloads for strict providers — see the config block above |
| `PRISM_REQUEST_TIMEOUT` | `30` | HTTP timeout in seconds for AI provider requests — increase for slow local models (e.g. Ollama on CPU) |

---

## Admin Screen — Configuring a Provider

1. Navigate to `/ptah-ai/models` (or click **AI Models** in the top navbar config dropdown)
2. Click **New configuration**
3. Fill in the form:
   - **Name** — internal identifier (e.g. `openai-production`)
   - **Provider** — select from the dropdown
   - **Model** — the model string (e.g. `gpt-4o`, `claude-3-5-sonnet-20241022`, `gemini-1.5-pro`, `llama3`)
   - **API Key** — your provider API key (stored encrypted)
   - **API Endpoint** — only for Ollama or custom providers (e.g. `http://localhost:11434`)
   - **Max tokens** — maximum response length (default: 1024)
   - **Temperature** — creativity 0.0–1.0 (default: 0.70)
   - **System prompt** — optional override for this specific config
   - **Active** — whether this config is available
   - **Default** — the config used by the chat widget
4. Click **Save**
5. Click **Set as default** if not already checked

### Provider-specific setup

#### OpenAI
- Get your API key at [platform.openai.com/api-keys](https://platform.openai.com/api-keys)
- Recommended models: `gpt-4o`, `gpt-4o-mini`, `gpt-4-turbo`

#### Anthropic
- Get your API key at [console.anthropic.com/account/keys](https://console.anthropic.com/account/keys)
- Recommended models: `claude-3-5-sonnet-20241022`, `claude-3-haiku-20240307`

#### Google Gemini
- Get your API key at [aistudio.google.com/app/apikey](https://aistudio.google.com/app/apikey)
- Recommended models: `gemini-1.5-pro`, `gemini-1.5-flash`

#### xAI (Grok)
- Get your API key at [console.x.ai](https://console.x.ai)
- Recommended models: `grok-2`, `grok-2-mini`
- Leave **API Endpoint** empty — the default (`https://api.x.ai/v1`) is correct
- Select the provider **xAI (Grok)**, not OpenAI: routing Grok through the OpenAI
  provider sends it to the Responses API, which x.ai does not implement

#### DeepSeek
- Get your API key at [platform.deepseek.com](https://platform.deepseek.com)
- Recommended models: `deepseek-chat`, `deepseek-reasoner`

#### OpenRouter
- Get your API key at [openrouter.ai/keys](https://openrouter.ai/keys)
- The model is the full slug, e.g. `anthropic/claude-3.5-sonnet`, `meta-llama/llama-3.1-70b-instruct`

#### Perplexity
- Get your API key at [perplexity.ai](https://www.perplexity.ai/settings/api)
- Recommended models: `sonar`, `sonar-pro`

#### z.ai (GLM)
- Get your API key at [z.ai](https://z.ai)
- Recommended models: `glm-4`, `glm-4-flash`
- **No streaming**: this provider ships no streaming handler, so the answer
  arrives all at once even with `PTAH_AI_STREAM=true`. Nothing to configure —
  ptah detects it and takes the non-streaming path.

#### OpenAI-compatible (custom endpoint)
- Use this for Together, Fireworks, Cerebras, SambaNova, vLLM, LM Studio,
  llama.cpp, LocalAI — see the endpoint table under **Providers**
- **Not** for Azure OpenAI, which uses a different auth header (see the note there)
- **API Endpoint is required** and must be the OpenAI-compatible base URL,
  usually ending in `/v1`
- The model is whatever that platform calls it, e.g.
  `meta-llama/Llama-3-70b-chat-hf` on Together
- Do **not** pick OpenAI with a custom endpoint: it targets the Responses API,
  which these platforms do not implement

#### Ollama (local / self-hosted)
- Install Ollama: [ollama.com](https://ollama.com)
- Pull a model: `ollama pull qwen2.5:3b`
- Set **API Endpoint** to `http://localhost:11434` (or your server's URL)
- No API key required — leave the API key field empty or enter any value

> **Do not add `/api` to the endpoint.** Prism appends the `api/chat` path
> itself, so `http://localhost:11434/api` becomes `.../api/api/chat` and every
> request 404s. Leaving the endpoint empty is also fine — the default is correct.

> **Running inside Docker?** If your Laravel application runs inside a Docker container, `localhost` resolves to the container itself — not your host machine. Use `http://host.docker.internal:11434` as the endpoint instead.

**Recommended models for Ollama** (best function-calling support):

| Model | Size | Function Calling | Notes |
|---|---|---|---|
| `qwen2.5:3b` | 1.9 GB | ✅ Good | Best 3B model, fast on CPU |
| `qwen2.5:7b` | 4.7 GB | ✅ Excellent | Best quality/size ratio |
| `deepseek-r1:7b` | 4.7 GB | ✅ Good | Advanced reasoning |
| `gemma3:4b` | 2.5 GB | ✅ Good | Efficient, good PT-BR |

> **Note:** Small models (`1b`, `3b`) may not reliably use function calling. If the model returns JSON text instead of calling tools, upgrade to a larger model (7b+).

> **Slow responses?** Local models run on CPU by default. The service automatically extends PHP's execution time limit to 5 minutes (`set_time_limit(300)`) to accommodate slow inference. You must also set `PRISM_REQUEST_TIMEOUT=300` in `.env` to extend the HTTP client timeout (default is 30 seconds):
>
> ```env
> PRISM_REQUEST_TIMEOUT=300
> ```

---

## Customising the System Prompt

The system prompt is resolved in this priority order:

1. The `system_prompt` field of the **active default provider config** (set in admin screen)
2. `config('ptah.ai_agent.system_prompt')` (from `config/ptah.php` or `.env`)

Example via `.env`:

```env
PTAH_AI_SYSTEM_PROMPT="You are Aria, a helpful assistant for Acme Corp's internal helpdesk. Answer questions about IT support, internal tools and company policies. Always be concise and professional."
```

---

## Custom Tools (Function Calling)

The AI Agent supports function calling via tools. Built-in tools are automatically registered:

| Tool | Description |
|---|---|
| `get_system_info` | Returns app name, Laravel version, PHP version and environment |
| `get_current_datetime` | Returns the current date and time |

### Creating a custom tool

Create a class in `app/Services/AI/Tools/` implementing `Ptah\Contracts\AiToolInterface`:

```php
<?php

namespace App\Services\AI\Tools;

use App\Models\Ticket;
use Ptah\Contracts\AiToolInterface;

class GetOpenTicketsTool implements AiToolInterface
{
    public function name(): string
    {
        return 'getOpenTickets'; // camelCase, used by the LLM to call the tool
    }

    public function description(): string
    {
        return 'Returns the number of support tickets filtered by status.';
    }

    public function parameters(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'status' => [
                    'type'        => 'string',
                    'description' => 'Filter by ticket status (open, closed, pending)',
                ],
            ],
            'required' => [], // empty = all params are optional
        ];
    }

    public function execute(array $arguments): array
    {
        $status = $arguments['status'] ?? 'open';
        $count  = Ticket::where('status', $status)->count();

        return [
            'status' => $status,
            'count'  => $count,
        ];
    }
}
```

### Registering the tool

In `config/ptah.php`, add the class name (string) to the `tools` array — **do not use `new`**:

```php
'ai_agent' => [
    'tools' => [
        App\Services\AI\Tools\GetOpenTicketsTool::class,
        App\Services\AI\Tools\GetUserInfoTool::class,
    ],
],
```

> **Important:** Register tools as class strings (`::class`), not instances (`new`). The service provider instantiates them via the Laravel container, which enables dependency injection if needed.

> **Authorization:** Tool `execute()` methods run with the same user context as the Livewire request. Apply your own authorization logic inside `execute()` as needed (e.g. `abort_if(!auth()->user()->can(...))`).

### Real-world example — Helpdesk stats tool

This example ships with the PetPlace demo app and provides the AI agent with live data:

```php
<?php

namespace App\Services\AI\Tools;

use App\Models\Agent;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use Ptah\Contracts\AiToolInterface;

class GetHelpDeskStatsTool implements AiToolInterface
{
    public function name(): string { return 'getHelpDeskStats'; }

    public function description(): string
    {
        return 'Returns helpdesk statistics: ticket counts by status/priority, list of agents, list of categories, and user count.';
    }

    public function parameters(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'include' => [
                    'type'        => 'string',
                    'description' => 'Comma-separated list: tickets, agents, categories, users. Leave empty for all.',
                ],
            ],
            'required' => [],
        ];
    }

    public function execute(array $arguments): array
    {
        $include = isset($arguments['include'])
            ? array_map('trim', explode(',', $arguments['include']))
            : ['tickets', 'agents', 'categories', 'users'];

        $result = [];

        if (in_array('tickets', $include)) {
            $result['tickets'] = [
                'total'          => Ticket::count(),
                'by_status'      => Ticket::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
                'resolved_today' => Ticket::whereDate('resolved_at', today())->count(),
                'created_today'  => Ticket::whereDate('created_at', today())->count(),
            ];
        }

        if (in_array('agents', $include)) {
            $result['agents'] = ['total' => Agent::count(), 'list' => Agent::select('id','name','email')->get()];
        }

        if (in_array('users', $include)) {
            $result['users'] = ['total' => User::count()];
        }

        return $result;
    }
}
```

**Example questions you can ask after registering this tool:**
- *"Quantos tickets estão abertos?"*
- *"Me dê um resumo geral do helpdesk"*
- *"Qual agente tem mais tickets abertos?"*
- *"Quantos usuários temos cadastrados?"*
- *"Quantos tickets foram resolvidos hoje?"*

---

## Rate Limiting

Rate limiting is session-based using the Laravel Cache driver. Each session is capped at `ptah.ai_agent.rate_limit` requests per minute (default: 30).

When the limit is exceeded, a friendly message is shown in the widget and `AiRateLimitException` is thrown internally.

To change the limit globally:

```env
PTAH_AI_RATE_LIMIT=10
```

---

## Conversation History

Conversations are stored in the `ptah_ai_conversations` table:

| Column | Type | Description |
|---|---|---|
| `id` | bigint | Primary key |
| `session_id` | string(40) | Laravel session ID (used for guest conversations) |
| `user_id` | bigint (nullable) | Foreign key to `users` — set for authenticated users |
| `title` | string(160) (nullable) | Auto-generated from the first message (`Str::limit($message, 60)`) |
| `messages` | json | Array of `{role, content}` objects |
| `provider_used` | string | Provider that handled the conversation |
| `model_used` | string | Model that handled the conversation |
| `tokens_used` | int | Total tokens consumed |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

### Per-user persistence

When the user is authenticated, conversations are scoped to their `user_id` rather than the session. This means:

- Conversations survive browser restarts and session expiry
- Up to the last **20 conversations** are listed in the history panel (sorted by most recent)
- Each conversation shows its auto-generated title and creation date

### History panel

Authenticated users see a **history** button (`⏱`) in the widget header. Clicking it opens a conversation list where they can:

- Browse previous conversations
- Click any entry to reload its full message history
- Click **New conversation** (✏) to start a fresh session

Guest users (unauthenticated) follow the old session-based flow — no history panel is shown.

### Message trimming

History is automatically trimmed to `ptah.ai_agent.max_history` messages per conversation (oldest messages are dropped when the limit is reached).

---

## Extending with Custom Providers

The module maps provider names to `Prism\Prism\Enums\Provider` enum constants internally (package `prism-php/prism` v0.100+, namespace `Prism\Prism`). To use a provider not in the enum, set the `api_endpoint` field in the admin screen (some Prism adapters support endpoint overrides).

Refer to the [prism-php/prism documentation](https://prism.echolabs.dev) for the full list of supported providers and configuration options.

---

## Security Considerations

- **API keys** are stored with Laravel's `encrypted` cast — they are AES-256 encrypted using your `APP_KEY`. Never commit `.env` to version control.
- **Access control** — the admin config screen (`/ptah-ai/models`) requires the user to pass `ptah_can('ai.config', 'read') || ptah_is_master()`. Register the `ai.config` page object in the permissions module and grant `read` on it, or ensure only master users access it.
- **Rate limiting** — the built-in session-based rate limit protects against accidental cost spikes. For production, tune `PTAH_AI_RATE_LIMIT` to match your expected usage.
- **Tool execution** — custom tool `execute()` methods run with the same user context as the Livewire request. Apply your own authorization checks inside `execute()` as needed.

---

## Troubleshooting

### Widget not appearing

- Confirm `PTAH_MODULE_AI_AGENT=true` is set in `.env`
- Run `php artisan config:clear` after changing `.env`
- Ensure at least one provider config exists and is marked as **Active** + **Default**

### "AI assistant not available" message

- The widget checks `AiProviderConfigService::hasActiveProvider()` on mount
- Create an active default provider config in `/ptah-ai/models`

### prism/prism class not found

- Run `composer require prism-php/prism`
- Run `composer dump-autoload`
- Ensure the package is `prism-php/prism` (not the old `echolabsdev/prism`). The namespace changed from `EchoLabs\Prism` to `Prism\Prism` in v0.70+.

### API errors

The chat now names the cause rather than showing a raw provider string, so start
from the message on screen:

| Message says | What to change |
|---|---|
| the credential was rejected | the **API Key** for this provider |
| the address was not found | the **API Endpoint** — check for a typo or a missing `/v1` |
| could not reach the provider | the endpoint, and whether the server is running (self-hosted / Ollama) |
| the provider is throttling | nothing — wait and retry |
| does not serve the configured model | the **Model** field; the name must be exactly what that platform calls it |
| refused the request format | nothing you did — a configuration problem; the log has the detail |
| unavailable right now | nothing — the provider is down, retry shortly |

Every failure is also logged with the classified `reason`, the HTTP `status` and
the provider's **raw response body**:

```
[Ptah AI] Provider call failed {"provider":"xai","model":"grok-2",
  "reason":"schema","status":422,"response_body":"{\"error\":\"Schema validation failed…\"}"}
```

Read that `response_body` first — it is the provider's own explanation, and it is
usually far more specific than anything else available. With `APP_DEBUG=true` it
is also appended to the message shown in the chat widget; with debug off it stays
in the log only, because a response body can carry internal detail.

Other things worth checking:

- For Ollama, ensure the server is running and the model is pulled: `ollama pull qwen2.5:3b`
- For Ollama inside Docker, use `http://host.docker.internal:11434` as the API endpoint instead of `localhost`
- Review Laravel logs: `storage/logs/laravel.log`

### "Unknown error", 400 or 422 from an OpenAI-compatible provider

Two causes, both fixed in 1.30.0 — if you see this on an older version, upgrade:

1. **The provider was set to `openai` with a custom endpoint.** That targets
   OpenAI's Responses API (`/v1/responses`), which other platforms do not
   implement. Use the matching provider (`xai`, `deepseek`, …) or
   `openai_compatible`.
2. **A tool with no arguments.** Its empty parameter list serialised as
   `"properties": []`, which strict providers reject as invalid JSON Schema.
   `PTAH_AI_NORMALIZE_TOOL_SCHEMA` (on by default) fixes this on the way out.

### Choosing between several providers

With more than one **active** provider configured, the chat panel shows a picker
above the message list. It starts on the provider flagged as default, so a user
who ignores it gets the same behaviour as before; the control does not render at
all when only one provider is configured.

The choice applies to the next message and is validated server-side: a provider
that has been switched off cannot be used even by a client still holding its id —
the request falls back to the default instead.

### Ollama returns JSON text instead of calling tools

The model is too small to support function calling via protocol. Upgrade to a larger model:

```bash
ollama pull qwen2.5:7b
```

Then update the **Model** field in `/ptah-ai/models`.

### Maximum execution time / HTTP timeout exceeded

Local models (Ollama) can be slow, especially on CPU. Two timeouts need to be extended:

**1. PHP execution time** — the service calls `set_time_limit(300)` automatically. If the error persists, also increase `max_execution_time` in your `php.ini`:

```ini
max_execution_time = 300
```

**2. HTTP client timeout** — the Prism HTTP client has a separate 30-second timeout controlled by `PRISM_REQUEST_TIMEOUT`. Add this to your `.env`:

```env
PRISM_REQUEST_TIMEOUT=300
```

Then run `php artisan config:clear`.

### Migrations not found

```bash
php artisan vendor:publish --tag=ptah-ai-agent
php artisan migrate
```
