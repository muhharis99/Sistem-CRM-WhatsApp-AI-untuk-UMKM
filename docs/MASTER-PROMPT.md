# MASTER PROMPT — Sistem CRM WhatsApp + AI untuk UMKM

Anda adalah senior software architect, senior PHP CodeIgniter 4 developer, Node.js engineer, UI/UX designer, database engineer, QA engineer, dan DevOps engineer. Bangun aplikasi production-ready bernama **CRM WhatsApp AI UMKM**.

## Tujuan Produk

Buat platform CRM yang membantu UMKM mengelola kontak/customer, percakapan WhatsApp, follow-up penjualan, broadcast, katalog, dan pekerjaan customer service dari satu dashboard. AI digunakan sebagai asisten, bukan sebagai pengganti kontrol manusia.

## Prinsip Utama

1. Produk harus benar-benar runnable, bukan mockup.
2. Gunakan CodeIgniter 4 untuk web application/API backend.
3. Gunakan Node.js + Baileys sebagai WhatsApp gateway terpisah.
4. Gunakan MySQL sebagai database utama.
5. Gunakan Bootstrap 5 untuk UI responsive.
6. Gunakan JavaScript modular dan AJAX/fetch untuk interaksi tanpa reload jika masuk akal.
7. Pisahkan controller, service, repository/model, validation, dan integration layer.
8. Jangan hard-code credential, token, API key, nomor WhatsApp, atau URL environment.
9. Semua konfigurasi sensitif menggunakan `.env`.
10. Semua fitur harus mempunyai state loading, empty state, success state, dan error state.
11. Utamakan keamanan, auditability, maintainability, dan performa.
12. Gunakan Bahasa Indonesia untuk UI default.

## Stack

### Web
- PHP 8.2+
- CodeIgniter 4
- MySQL 8+
- Bootstrap 5
- Bootstrap Icons
- DataTables
- Chart.js
- SweetAlert2
- Select2/Choices bila diperlukan

### WhatsApp
- Node.js 20+
- Baileys
- REST API antara Node service dan CodeIgniter
- WebSocket/SSE bila dibutuhkan untuk realtime inbox

### Optional infrastructure
- Redis untuk queue/cache/rate limit
- Supervisor/systemd untuk worker
- Docker Compose untuk local/production reproducibility

### AI
Buat provider abstraction sehingga dapat memakai provider AI berbeda tanpa mengubah business logic. Implementasi awal harus mempunyai interface/service untuk:
- generate reply suggestion
- summarize conversation
- classify intent
- extract customer sentiment
- extract lead information
- generate follow-up suggestion

AI harus mempunyai guardrail:
- jangan mengirim pesan otomatis tanpa rule yang diaktifkan admin
- jangan mengarang harga, stok, kebijakan, atau janji bisnis
- gunakan product/customer/business context yang tersedia
- tandai output AI sebagai AI-generated sebelum human approval jika mode approval aktif

# 1. MULTI-TENANT / BUSINESS PROFILE

Sistem harus dapat digunakan lebih dari satu bisnis.

Entitas minimum:
- businesses
- users
- roles
- permissions
- user_businesses
- business_settings

Setiap data bisnis wajib terisolasi berdasarkan `business_id`.

Role minimum:
- owner
- admin
- sales
- customer_service
- viewer

# 2. AUTHENTICATION

Implementasikan:
- login
- logout
- session management
- remember me bila aman
- password hashing
- forgot/reset password dengan token
- profile
- change password
- role/permission middleware

# 3. DASHBOARD

Dashboard modern, bersih, responsive.

KPI:
- total customer
- customer baru hari ini
- percakapan aktif
- unread inbox
- lead baru
- deal won bulan ini
- revenue pipeline
- broadcast terkirim
- delivery/read/failed

Charts:
- leads per hari
- conversations per hari
- sales funnel
- customer source
- top products
- agent performance

Tambahkan quick actions:
- tambah customer
- kirim pesan
- buat broadcast
- tambah deal
- tambah produk

# 4. CUSTOMER / CONTACT CRM

Data:
- nama
- nomor WhatsApp
- email
- gender optional
- tanggal lahir optional
- alamat optional
- kota
- company optional
- source
- status
- owner/assigned agent
- tags
- notes
- last interaction
- total conversation
- total spending
- created_at

Fitur:
- CRUD
- search
- filter
- sort
- pagination
- import CSV/XLSX bila memungkinkan
- export CSV
- duplicate detection berdasarkan nomor telepon
- customer detail 360°
- timeline activity
- notes
- tags
- assign agent

Normalisasi nomor telepon harus mendukung input `08xxxxxxxxxx` dan mengubah internal canonical format sesuai konfigurasi negara.

# 5. WHATSAPP DEVICE MANAGEMENT

Dashboard untuk:
- connect device
- QR code
- connection status
- phone number
- session status
- reconnect
- logout device
- last connected
- webhook health

Status:
- disconnected
- connecting
- qr_required
- connected
- logged_out
- error

Node Baileys service harus memisahkan session per business/device dan tidak menaruh authentication state di Git.

# 6. WHATSAPP INBOX

Buat tampilan seperti modern customer service inbox.

Layout desktop:
- kiri: conversation list
- tengah: message thread
- kanan: customer profile / CRM context

Conversation list:
- avatar
- name
- phone
- last message
- timestamp
- unread badge
- assigned agent
- tag
- priority

Message support:
- text
- image
- document
- audio
- video
- sticker bila didukung
- reply/quote
- media preview
- delivery state

Action:
- assign conversation
- close/reopen
- mark unread
- add tag
- add note
- transfer agent
- create deal
- AI summarize
- AI suggest reply
- send message

Realtime behavior harus dirancang sehingga pesan masuk dapat muncul tanpa refresh manual.

# 7. CONVERSATION MANAGEMENT

Status conversation:
- open
- pending
- resolved
- archived

Tambahkan:
- priority low/normal/high/urgent
- SLA timer optional
- assignment
- internal notes yang tidak dikirim ke customer
- customer timeline

# 8. AI ASSISTANT

Fitur:

## AI Reply Suggestion
Input:
- recent conversation
- customer profile
- product catalog context
- business tone setting

Output:
- suggested reply
- confidence/quality indicator bila provider mendukung
- regenerate
- shorten
- make polite
- make casual
- translate

AI tidak boleh mengirim otomatis kecuali business rule mengizinkan.

## Conversation Summary
Buat ringkasan:
- customer intent
- masalah
- produk yang diminati
- budget jika disebut
- urgency
- objections
- next action

## Intent Classification
Contoh:
- tanya produk
- tanya harga
- order
- komplain
- follow up
- pembayaran
- pengiriman
- lainnya

## Lead Extraction
Ekstrak bila tersedia:
- need
- product
- quantity
- estimated budget
- timeline
- location

## Smart Follow-up
Sarankan follow-up berdasarkan:
- customer belum dibalas
- customer belum melakukan pembayaran
- quote belum dikonfirmasi
- abandoned conversation

# 9. PRODUCT CATALOG

Data:
- SKU
- name
- description
- price
- cost optional
- stock optional
- unit
- category
- active
- image

Fitur:
- CRUD
- categories
- search
- filter
- stock status
- attach product context ke AI

# 10. SALES PIPELINE

Pipeline Kanban:
- new
- contacted
- qualified
- proposal
- negotiation
- won
- lost

Deal:
- customer
- title
- value
- pipeline stage
- probability
- expected close date
- assigned user
- source
- notes

Drag & drop stage harus update backend secara aman.

# 11. BROADCAST

Fitur:
- create campaign
- customer segment
- tags filter
- preview
- template
- scheduling
- rate limit
- campaign status
- delivery statistics

Status:
- draft
- scheduled
- processing
- completed
- cancelled
- failed

Jangan mengimplementasikan mekanisme spam. Sertakan opt-out/stop-list dan rate limiting.

# 12. MESSAGE TEMPLATES

CRUD template:
- name
- category
- body
- variables
- active

Variable examples:
- {{customer_name}}
- {{business_name}}
- {{order_number}}
- {{product_name}}

Implementasikan renderer aman dan validasi variable.

# 13. SCHEDULED MESSAGE

User dapat menjadwalkan pesan ke customer atau segment.

Fitur:
- date/time
- timezone
- template/message
- retry policy
- status
- error log

# 14. AUTOMATION RULES

Buat automation builder sederhana:

TRIGGER:
- incoming_message
- new_customer
- tag_added
- deal_stage_changed
- no_reply_for_x_hours
- scheduled_time

CONDITION:
- tag
- customer source
- conversation status
- product
- deal stage

ACTION:
- send template
- assign agent
- add tag
- create task
- AI classify
- create follow-up

Semua automation harus mempunyai enable/disable switch dan execution log.

# 15. TASK / FOLLOW-UP

Task:
- title
- customer
- deal
- assigned user
- due date
- priority
- status
- notes

Dashboard reminder untuk task overdue.

# 16. ANALYTICS

Laporan:
- customer growth
- incoming/outgoing messages
- response time
- agent performance
- funnel conversion
- won/lost deal
- campaign performance
- top customers
- top products
- AI usage

Filter:
- today
- yesterday
- 7 days
- 30 days
- this month
- custom date range

Export CSV bila relevan.

# 17. AUDIT LOG

Catat aktivitas penting:
- login
- logout
- create/update/delete customer
- send message
- broadcast
- change settings
- connect/disconnect WhatsApp
- AI action
- automation execution

Simpan:
- user
- business
- action
- entity
- entity_id
- metadata JSON
- IP
- user agent
- timestamp

# 18. NOTIFICATIONS

Buat internal notification center untuk:
- new message
- assigned conversation
- overdue task
- automation error
- WhatsApp disconnect
- campaign selesai

# 19. API

Buat API versioned:
`/api/v1/...`

Endpoint minimal:
- auth
- customers
- conversations
- messages
- products
- deals
- campaigns
- templates
- automations
- notifications
- whatsapp/devices
- ai

Semua request/response JSON terstruktur.

# 20. WEBHOOK WHATSAPP

Node.js service menerima event Baileys dan mengirim ke backend:
- message_received
- message_sent
- message_status
- connection_update
- qr
- contact_update bila tersedia

Pastikan webhook idempotent agar event duplikat tidak membuat data ganda.

# 21. DATABASE

Buat migrations dan seeders lengkap.

Tabel minimum:
- businesses
- business_settings
- users
- roles
- permissions
- user_businesses
- customers
- customer_tags
- tags
- conversations
- messages
- message_attachments
- whatsapp_devices
- whatsapp_events
- products
- product_categories
- deals
- deal_stages
- campaigns
- campaign_recipients
- message_templates
- scheduled_messages
- automation_rules
- automation_logs
- tasks
- notifications
- audit_logs
- ai_requests
- ai_usage_logs

Gunakan foreign keys, indexes, unique constraints, created_at, updated_at dan soft delete jika relevan.

Index penting:
- business_id
- phone
- conversation status
- assigned_user_id
- message timestamp
- campaign status
- scheduled_at

# 22. UI/UX

Desain harus terasa seperti SaaS CRM modern, bukan template admin lama.

Gunakan:
- sidebar collapsible
- topbar
- responsive mobile navigation
- cards dengan hierarchy jelas
- clean tables
- compact badges
- modern modal
- empty states
- skeleton/loading
- toast notification

Halaman minimum:
- Login
- Dashboard
- Inbox
- Customers
- Customer Detail
- Products
- Pipeline
- Campaigns
- Templates
- Automations
- Tasks
- Analytics
- WhatsApp Devices
- Users/Roles
- Settings
- Audit Logs

# 23. SECURITY

Wajib:
- CSRF
- XSS escaping
- SQL injection protection
- secure password hash
- authorization per role/business
- input validation
- rate limiting untuk endpoint sensitif
- webhook signature/shared secret
- secret masking
- upload validation
- no credentials committed

# 24. ERROR HANDLING & OBSERVABILITY

Semua integration error harus dicatat secara terstruktur.

Buat:
- application log
- WhatsApp gateway log
- AI request error log
- automation execution log

UI menampilkan pesan ramah, log menyimpan detail teknis.

# 25. TESTING

Minimal:
- model/service unit test
- authentication test
- authorization test
- customer CRUD test
- message persistence test
- webhook idempotency test
- AI service mock test
- campaign recipient generation test

# 26. DEVOPS

Sediakan:
- `.env.example`
- `composer.json`
- `package.json` untuk Node service
- database migration/seeder
- setup documentation
- local development instructions
- production deployment instructions
- optional `docker-compose.yml`
- process manager example untuk Node gateway

Jangan commit:
- `.env`
- WhatsApp auth/session data
- API keys
- private tokens
- uploaded customer media

# 27. ACCEPTANCE CRITERIA

Aplikasi dianggap selesai untuk MVP bila:

1. User dapat login.
2. Owner dapat membuat business profile.
3. User dapat menambah customer.
4. User dapat menghubungkan WhatsApp melalui QR.
5. Pesan masuk muncul di Inbox.
6. User dapat mengirim pesan dari Inbox.
7. Percakapan tersimpan lengkap.
8. User dapat memakai AI untuk membuat draft balasan.
9. User dapat membuat tag.
10. User dapat membuat deal dan memindahkan stage.
11. User dapat membuat campaign.
12. User dapat melihat statistik campaign.
13. User dapat membuat template.
14. User dapat menjadwalkan pesan.
15. User dapat melihat analytics.
16. Semua aktivitas kritis tercatat di audit log.
17. Tidak ada secret hard-coded.
18. Migration dan seeder dapat dijalankan dari environment baru.
19. README menyediakan langkah instalasi dari nol.
20. Codebase siap dikembangkan lebih lanjut tanpa membuat controller menjadi monolitik.

# 28. IMPLEMENTATION ORDER

Kerjakan bertahap:

Phase 1 — Foundation
- CI4 setup
- environment
- database
- authentication
- roles
- layout

Phase 2 — CRM
- customers
- tags
- products
- notes

Phase 3 — WhatsApp
- Node gateway
- device session
- QR
- webhook
- inbox
- message history

Phase 4 — Sales
- pipeline
- deals
- tasks

Phase 5 — AI
- provider abstraction
- reply suggestion
- summary
- intent
- lead extraction

Phase 6 — Engagement
- templates
- broadcast
- scheduler
- automation

Phase 7 — Analytics & Hardening
- reports
- audit
- notifications
- tests
- security review
- performance

# 29. CODING RULES

- Jangan membuat file besar yang memuat seluruh business logic.
- Reuse service dan component.
- Gunakan naming yang jelas dan konsisten.
- Gunakan database transactions untuk operasi multi-table penting.
- Gunakan pagination untuk data besar.
- Gunakan eager loading/query terukur untuk dashboard.
- Jangan melakukan N+1 query.
- Semua asynchronous process yang berat harus melalui queue bila infrastructure tersedia.
- Gunakan UTC atau timezone aplikasi yang konsisten pada penyimpanan; tampilkan sesuai timezone business.
- Dokumentasikan endpoint dan environment variable.
- Jangan membuat fitur palsu yang hanya tampak bekerja di UI.

# 30. OUTPUT YANG DIHARAPKAN DARI CODING AGENT

Setelah implementasi:

1. Pastikan seluruh source code tersimpan di repository.
2. Jalankan migration dan seed.
3. Tambahkan README yang benar-benar dapat diikuti developer baru.
4. Tambahkan `.env.example`.
5. Tambahkan contoh API request.
6. Tambahkan contoh konfigurasi Node WhatsApp gateway.
7. Tambahkan test untuk fungsi kritis.
8. Review security dan permission.
9. Periksa bahwa project tidak mengandung credential rahasia.
10. Buat commit terpisah per milestone dengan message yang jelas.

Jangan berhenti pada pembuatan desain UI. Implementasikan backend, database, frontend, integrasi WhatsApp, dan service AI sampai fitur MVP benar-benar dapat dijalankan.
