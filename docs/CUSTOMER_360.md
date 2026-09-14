# Customer 360 + Sales Workspace

Modul ini menyediakan satu workspace tenant-scoped untuk melihat dan mengelola seluruh konteks customer.

## UI

- `GET /customers/{customerId}`
- Profil customer
- Statistik WhatsApp dan sales
- Tags
- Conversation WhatsApp
- Activity timeline
- Customer notes
- Tasks/follow-up
- Deals + stage/status
- AI lead insight
- Shortcut menuju Inbox

## API

### Workspace overview
`GET /api/customers/{id}/workspace`

Mengembalikan:

- `customer`
- `tags`
- `notes`
- `conversations`
- `deals`
- `tasks`
- `activities`
- `stats`

### Notes

`POST /api/customers/{id}/notes`

Body:
```json
{"body":"Follow up penawaran hari Jumat."}
```

`DELETE /api/customers/{id}/notes/{noteId}`

### Tasks

`POST /api/customers/{id}/tasks`

Body contoh:
```json
{"title":"Follow up customer","due_at":"2026-09-15 10:00:00","priority":"HIGH"}
```

`PATCH /api/customers/{id}/tasks/{taskId}`

Body dapat memperbarui `status`, `priority`, `due_at`, atau `assigned_to`.

### Deals

`POST /api/customers/{id}/deals`

Body contoh:
```json
{"pipeline_id":1,"stage_id":1,"title":"Paket Premium","value":2500000,"expected_close_date":"2026-09-30"}
```

`PATCH /api/customers/{id}/deals/{dealId}`

Dapat memperbarui `stage_id`, `status`, `value`, `expected_close_date`, dan `owner_id`.

## Tenant Isolation

Semua query menggunakan `tenant_id` dari session server-side. ID customer, note, task, deal, tag, pipeline, dan conversation diverifikasi terhadap tenant sebelum operasi.

## Activity Timeline

Perubahan penting seperti membuat note, membuat task, membuat deal, dan perubahan deal dicatat ke tabel `activities` agar customer mempunyai audit-like timeline yang mudah dibaca.

## Database

Modul memakai tabel yang sudah tersedia pada CRM Sales Schema:

- `customer_notes`
- `customer_tags`
- `tasks`
- `deals`
- `activities`
- `conversations`
- `messages`

Tidak diperlukan migration tambahan untuk modul ini.
