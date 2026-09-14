# CRM Analytics & KPI

## Dashboard

Open `/analytics` after login.

The dashboard is tenant-scoped and accepts a date range through:

```text
GET /api/analytics/dashboard?from=2026-09-01&to=2026-09-14
```

## KPI

The API returns:

- customers and new customers
- conversations and active conversations
- incoming/outgoing messages
- open deals and open pipeline value
- won value, won deals, and deal conversion rate
- campaign count, delivery rate, read rate, and failures
- AI request count, average confidence, and human-handoff rate
- daily incoming/outgoing message series
- open pipeline grouped by stage
- agent conversation and message performance

All queries are restricted to the authenticated session tenant. The dashboard reads existing CRM tables; no additional migration is required.

## Performance notes

For production-scale tenants, add covering indexes for high-volume reporting columns (`tenant_id`, message `timestamp`, deal `status/updated_at`, campaign `created_at`, and AI log `created_at`) and consider a daily aggregate table once message volume becomes large.
