# Inbox Realtime Dashboard

Modul Inbox sekarang menyediakan dashboard percakapan WhatsApp berbasis Bootstrap 5 dan Socket.IO.

## Fitur

- Conversation list dengan pencarian nama/nomor.
- Filter status dan priority.
- Thread chat dengan bubble incoming/outgoing.
- Composer multiline: Enter kirim, Shift+Enter baris baru.
- Mark conversation sebagai read.
- Update status conversation.
- AI Suggestion sebagai draft, bukan auto-send.
- AI conversation analysis.
- Toggle AI/Human handoff per conversation.
- Responsive layout untuk desktop dan mobile.
- Realtime event dari gateway Baileys.
- Socket.IO tenant room tidak dapat dipilih bebas oleh browser; tenant ditentukan oleh signed short-lived realtime token dari CRM.

## Endpoint

`GET /inbox` — dashboard UI.

`GET /api/inbox/conversations` — daftar conversation, mendukung `status` dan `search`.

`GET /api/inbox/conversations/{id}/messages` — detail conversation + pesan.

`GET /api/inbox/realtime-token` — token Socket.IO berumur 5 menit untuk tenant pada session login.

`POST /api/inbox/conversations/{id}/read` — reset unread count.

`PATCH /api/inbox/conversations/{id}` — update status/assignment/AI/priority.

`POST /api/inbox/conversations/{id}/send` — kirim pesan melalui gateway Baileys.

## Realtime security

Browser tidak menerima `WHATSAPP_GATEWAY_SECRET`. CRM membuat token `payload.signature` menggunakan HMAC-SHA256 dengan secret yang sama. Gateway memverifikasi signature dan expiry sebelum memasukkan socket ke room `tenant:{tenant_id}`.

Karena token hanya berlaku singkat, client perlu mengambil token baru jika koneksi realtime dibuat kembali setelah token kedaluwarsa.

## Flow inbound

Baileys -> gateway -> CRM webhook -> persist message -> automation/AI -> gateway Socket.IO -> browser.

Message dipersist terlebih dahulu sebelum event realtime dikirim ke browser sehingga UI tidak menampilkan event yang belum masuk database.

## Flow outbound

Browser -> CRM Inbox API -> `WhatsAppGatewayService` -> gateway -> Baileys -> WhatsApp.

AI Suggest hanya mengisi composer. Pengiriman tetap melewati endpoint outbound yang sama.

## Production notes

Pastikan `WHATSAPP_GATEWAY_SECRET` identik di CRM dan Node gateway. Pastikan `WHATSAPP_WEBHOOK_SECRET` identik untuk webhook relay. Gateway default CRM URL adalah `http://127.0.0.1:6060`.

Runtime integration test dengan database, Redis, session login, Socket.IO dan akun WhatsApp nyata tetap harus dijalankan pada environment deployment.
