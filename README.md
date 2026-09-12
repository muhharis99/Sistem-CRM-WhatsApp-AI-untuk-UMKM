# Sistem CRM WhatsApp AI untuk UMKM

Platform CRM omnichannel berbasis WhatsApp untuk UMKM dengan AI-assisted customer service, sales pipeline, broadcast, scheduler, customer database, dan analytics.

## Arsitektur

- **Backend:** CodeIgniter 4 / PHP 8.2+
- **Database:** MySQL 8+
- **WhatsApp Gateway:** Node.js + Baileys
- **Queue/Cache:** Redis (optional, recommended for production)
- **Frontend:** Bootstrap 5, DataTables, Chart.js, SweetAlert2
- **AI:** Provider-agnostic service melalui REST API
- **Auth:** Session-based authentication + role/permission

## Modul MVP

1. Dashboard KPI
2. Customer/Contact Management
3. WhatsApp Inbox
4. Conversation & Message History
5. AI Reply Suggestion
6. AI Conversation Summary
7. Tags & Segmentation
8. Sales Pipeline / Kanban
9. Broadcast Campaign
10. Scheduled Messages
11. Product Catalog
12. Basic Analytics
13. User, Role & Permission
14. WhatsApp Device/Session Management
15. API & Webhook
16. Activity/Audit Log

## Target structure

```text
/app
  /Controllers
  /Models
  /Services
  /Libraries
  /Database
/node-whatsapp
  /src
  /config
/public
/resources
  /views
/docs
/tests
```

## Development principle

- Gunakan clean architecture sederhana dan service layer.
- Jangan menaruh business logic berat di controller.
- Semua credential/API key menggunakan `.env`.
- WhatsApp gateway tidak menyimpan credential sensitif di source code.
- Semua endpoint mutasi memakai validasi input dan CSRF/auth sesuai konteks.
- Semua timestamp database disimpan konsisten dan ditampilkan sesuai timezone tenant.

## Status

Bootstrap repository / architecture specification.
