# Queue & Broadcast Worker

Modul ini menangani broadcast WhatsApp secara asynchronous menggunakan Redis + BullMQ.

## Alur

```text
POST /api/campaigns/{id}/enqueue
        |
        v
CI4 membuat campaign_recipients + opt-out filter
        |
        v
Gateway /api/queue/campaigns/enqueue
        |
        v
Redis / BullMQ
        |
        v
broadcast-worker.js
        |
        +--> atomic claim ke CI4
        +--> opt-out check kedua
        +--> Redis rate limiter per campaign
        +--> Baileys sendText
        +--> mark SENT / PENDING / FAILED
```

## Menjalankan

Pastikan Redis hidup, lalu di `whatsapp-gateway`:

```bash
npm install
npm run start
node src/queue/broadcast-worker.js
```

Gunakan secret yang sama antara `.env` CodeIgniter dan `.env` gateway:

```env
WHATSAPP_GATEWAY_SECRET=...
WHATSAPP_WEBHOOK_SECRET=...
QUEUE_INTERNAL_SECRET=...
```

## API

Enqueue campaign:

```http
POST /api/campaigns/{id}/enqueue
```

Status queue:

```http
GET /api/campaigns/{id}/queue-status
```

Endpoint claim/complete hanya untuk worker dan dilindungi `X-Queue-Internal-Secret`:

```http
POST /api/internal/campaign-recipients/{id}/claim
POST /api/internal/campaign-recipients/{id}/complete
```

## Safety

- Campaign recipient memakai `jobId` deterministik: `campaign:{campaignId}:recipient:{recipientId}`.
- Recipient hanya boleh berubah dari `PENDING` ke `PROCESSING` melalui transaksi + row lock.
- Opt-out diperiksa saat pembuatan recipient dan diperiksa ulang sebelum pengiriman.
- Retry menggunakan exponential backoff BullMQ; status recipient dikembalikan ke `PENDING` sampai batas percobaan tercapai.
- Rate limit menggunakan bucket Redis per campaign per menit.
- Redis dan queue secret tidak pernah dikirim ke browser.

## Catatan produksi

Pisahkan proses gateway HTTP dan worker menjadi process/service terpisah pada supervisor atau container. Satu gateway HTTP harus menjadi pemilik session Baileys, sedangkan worker cukup memanggil endpoint send internal gateway.
