# Automation Engine

Automation Engine menjalankan workflow tenant berdasarkan event CRM/WhatsApp.

## Trigger

- `incoming_message`
- `new_customer`
- `tag_added`
- `deal_stage_changed`
- `no_reply_for_x_hours`
- `scheduled_time`

Saat ini webhook WhatsApp sudah memicu `incoming_message` setelah pesan disimpan oleh `CrmMessageService`.

## Conditions

Format sederhana menggunakan dot-path payload:

```json
{
  "message.text": {"operator":"CONTAINS","value":"harga"},
  "customer.status": "ACTIVE"
}
```

Operator yang didukung: `EQUALS`, `NOT_EQUALS`, `CONTAINS`, `STARTS_WITH`, `IN`, dan `NOT_EMPTY`.

## Actions

- `TAG_CUSTOMER`
- `CREATE_TASK`
- `CREATE_DEAL`
- `ASSIGN_OWNER`
- `SCHEDULE_MESSAGE`
- `ADD_NOTE`

Contoh automation:

```json
{
  "name": "Lead Harga",
  "trigger_event": "incoming_message",
  "conditions": {
    "text": {"operator":"CONTAINS","value":"harga"}
  },
  "actions": [
    {"type":"TAG_CUSTOMER","tag_id":3},
    {"type":"CREATE_TASK","title":"Follow up lead harga","priority":"HIGH"}
  ]
}
```

## Idempotency

Execution dikunci dengan `(tenant_id, automation_rule_id, event_id)`. Event yang sama tidak dieksekusi dua kali.

## Security

Semua rule dicari berdasarkan `tenant_id`. Action yang membuat data juga menulis `tenant_id` dari event, bukan dari input browser.
