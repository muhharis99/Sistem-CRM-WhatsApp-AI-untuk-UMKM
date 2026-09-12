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
```

The WhatsApp socket lifecycle is intentionally isolated in Node.js. CodeIgniter never holds a Baileys socket.

## Next phases

CRM inbox UI/realtime persistence, customer/tags/sales modules, AI provider abstraction + knowledge retrieval, queue-backed broadcast/automation, analytics, production security hardening and integration tests.
