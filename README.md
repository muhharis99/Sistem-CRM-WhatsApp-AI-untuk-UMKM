# CRM WhatsApp + AI untuk UMKM

Production-oriented SaaS foundation for UMKM CRM using CodeIgniter 4, MySQL/MariaDB, Node.js, Socket.IO and the official WhiskeySockets Baileys package.

## Current implementation

### Phase 1 — Foundation
- CodeIgniter 4 application bootstrap
- MySQL migration for tenants, RBAC, WhatsApp devices, customers, conversations, messages and audit logs
- Session-based authentication skeleton with password hashing
- Tenant-aware API foundation
- Node.js WhatsApp gateway isolated from CodeIgniter
- Baileys persistent multi-session manager using `tenant_id/device_id` directories
- QR pairing endpoint + Socket.IO events
- Reconnect with exponential backoff and logout detection
- Incoming message event relay to CodeIgniter webhook
- Outgoing text message endpoint
- Gateway secret + webhook secret
- Graceful shutdown
- Seeder with initial RBAC roles/permissions and demo tenant

### Phase 2 — CRM & Sales
- Customer CRUD and WhatsApp phone normalization
- Customer tags and tenant-safe attachment
- Conversation and message persistence
- Realtime-ready WhatsApp Inbox
- Outgoing Inbox reply through Node.js/Baileys
- Sales pipelines, stages, deals and basic tasks schema

### Phase 5 — AI Assistant foundation
- Provider abstraction via `AIProviderInterface`
- OpenAI Responses API provider
- Knowledge base, documents and chunks schema
- Tenant-scoped knowledge retrieval
- AI reply suggestion
- Conversation summary/intent/sentiment/lead score/next action analysis
- AI usage logging
- Human handoff flagging that disables `ai_enabled`
- AI endpoints for knowledge, suggestions, analysis and handoff

## Run CodeIgniter

```bash
composer install
cp .env.example .env
php spark migrate
php spark db:seed DatabaseSeeder
php spark serve
```

## Run WhatsApp gateway

```bash
cd whatsapp-gateway
npm install
cp .env.example .env
npm start
```

The gateway stores Baileys auth state under `whatsapp-gateway/sessions/` and never commits it to Git.

## AI configuration

Set these in `.env`:

```env
AI_PROVIDER=openai
AI_API_KEY=your-key
AI_MODEL=gpt-5.6-luna
```

AI does not send WhatsApp messages automatically in the current implementation. The suggestion endpoint returns a draft for human approval. Conversation analysis can disable AI for a conversation when a human handoff is required.

## AI endpoints

```text
GET    /api/ai/knowledge
POST   /api/ai/knowledge
POST   /api/ai/conversations/{id}/suggest
POST   /api/ai/conversations/{id}/analyze
PATCH  /api/ai/conversations/{id}/handoff
```

## Demo seed

The seed creates `admin@example.test` with password `ChangeMe123!`. Change or remove this account before any non-development deployment.

## Architecture

```text
Browser / Bootstrap 5
        |
        v
CodeIgniter 4 CRM  <----> MySQL / MariaDB
        ^                       ^
        | REST + signed webhook |
        v                       |
Node.js WhatsApp Gateway ------+
        |
        v
@whiskeysockets/baileys

                +----------------+
                | AI Service     |
                | Provider Abstr.|
                +-------+--------+
                        |
                        v
                 Knowledge Base
                        |
                        v
                    AI Provider
```

The WhatsApp socket lifecycle is intentionally isolated in Node.js. CodeIgniter never holds a Baileys socket.

## Next phases

Product catalog integration into AI, AI UI actions in Inbox, templates, queue-backed broadcast/scheduler/automation, analytics, production security hardening and integration tests.
