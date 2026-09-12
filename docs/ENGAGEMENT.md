# Engagement Engine

Modul ini menambahkan template pesan, campaign/broadcast, scheduled message, automation rule, execution-safe opt-out, dan fondasi rate limiting.

## Template

`message_templates` menyimpan body dan daftar variable. Variable didukung dengan format `{{customer_name}}`. Renderer tidak mengeksekusi HTML, PHP, JavaScript, atau expression; variable yang tidak tersedia dibiarkan apa adanya.

## Campaign

Campaign selalu dimulai sebagai `DRAFT`. Segment disimpan sebagai JSON agar filter dapat dikembangkan tanpa mengubah kontrak API. Sebelum proses pengiriman, recipient harus dibuat ulang/di-validasi terhadap tenant dan opt-out list.

Status: `DRAFT`, `SCHEDULED`, `PROCESSING`, `COMPLETED`, `CANCELLED`, `FAILED`.

`rate_limit_per_minute` adalah batas per campaign, bukan mekanisme spam. Implementasi worker wajib menghormati opt-out, device availability, retry limit, dan backoff.

## Scheduled messages

Scheduled message hanya membuat pekerjaan berstatus `PENDING`. Worker terpisah harus mengambil record yang due, melakukan atomic claim, mengirim melalui WhatsApp gateway, lalu menyimpan hasil. Jangan melakukan pengiriman langsung dari request HTTP.

## Automation

Automation default `enabled=0`. Trigger yang diizinkan:
- incoming_message
- new_customer
- tag_added
- deal_stage_changed
- no_reply_for_x_hours
- scheduled_time

Action JSON divalidasi oleh executor sebelum dijalankan. Setiap execution wajib mempunyai idempotency/event id dan audit/automation log.

## Opt-out

`opt_outs` adalah stop-list per tenant/customer/channel. Broadcast dan automation yang mengirim WhatsApp wajib mengecualikan customer pada stop-list sebelum enqueue dan memeriksa ulang sebelum send.

## Queue

Redis/queue worker belum mengirim pesan pada migration ini. Ini disengaja: database contract dipasang lebih dulu sehingga worker Node/PHP berikutnya dapat diimplementasikan tanpa mengubah data model.
