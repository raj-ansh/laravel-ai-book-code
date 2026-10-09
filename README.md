# Laravel + AI: companion code

The complete companion application for the book **Laravel + AI:
Building Intelligent Applications with Modern Laravel** by Rajkumar
Panda (two volumes).

`acme-ai` is one Laravel application for a fictional online store,
Acme, grown chapter by chapter. Every code listing in the book comes
from this repository, and every chapter's tests live in
`tests/Feature/ChNN`.

## Versions

The code was run and tested against:

| Component | Version |
|---|---|
| PHP | 8.5 (the AI SDK needs 8.3 or later) |
| Laravel | 13.34 |
| Laravel AI SDK (`laravel/ai`) | 1.0.1 |
| Laravel MCP (`laravel/mcp`) | 1.0.1 |
| Pest | 5.3 |
| PostgreSQL + pgvector | `pgvector/pgvector:pg16` (from Chapter 27) |
| Ollama (local models) | `qwen2.5:1.5b`, `nomic-embed-text`; `qwen3.5:0.8b` for vision |

## Getting started

```bash
git clone https://github.com/raj-ansh/laravel-ai-book-code.git
cd laravel-ai-book-code
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
```

The tests need no API key and no network: every test fakes its AI
calls (Chapter 47 explains how).

```bash
php artisan test --compact
php artisan test --compact tests/Feature/Ch05   # one chapter
```

On SQLite (the default), the vector-search tests skip themselves. To
run them, use PostgreSQL with the pgvector extension, as Chapter 28
describes.

## Running against a model

Set a provider in `.env`. For local models with Ollama (Chapter 4):

```env
AI_PROVIDER=ollama
OLLAMA_URL=http://localhost:11434
OLLAMA_TEXT_MODEL=qwen2.5:1.5b
OLLAMA_CHEAP_MODEL=qwen2.5:1.5b
AI_EMBEDDING_PROVIDER=ollama
OLLAMA_EMBEDDING_MODEL=nomic-embed-text
```

Hosted providers (OpenAI, Anthropic, Gemini and others) need their API
key in `.env`. Calls to hosted providers cost money.

Each chapter shows the commands it adds, for example:

```bash
php artisan ai:anatomy "What is an index?"        # Chapter 2
php artisan knowledge:ask priya@brightlite.example \
    "Which days do bulk deliveries arrive?"      # Chapter 52
```

Some chapters seed their own data (for example `TenantSeeder` in
Chapter 43 and `KnowledgePlatformSeeder` in Chapter 51).

## Where things are

| Path | Contents |
|---|---|
| `app/Ai/Agents` | Agents: instructions, schemas, tools |
| `app/Ai/Tools` | Tools agents can call |
| `app/Ai/Middleware` | Agent middleware: guards, pseudonymisation, logging |
| `app/Services` | Services that orchestrate and check AI work |
| `app/Evaluation` | Evaluation suites (Chapter 48) |
| `resources/eval` | Evaluation datasets |
| `resources/samples` | Sample documents, photos and data |
| `tests/Feature/ChNN` | Each chapter's tests |
| `tests/Live` | Optional tests against a real local model |

## Licence

MIT: see [LICENSE](LICENSE). The book's text and figures are not part
of this licence.
