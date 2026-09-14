# AI Agent Runtime

AI Agent mengubah pesan WhatsApp masuk menjadi balasan otomatis yang aman melalui automation engine dan scheduled-message worker.

## Flow

```text
WhatsApp message.received
        |
        v
CrmMessageService
        |
        v
AutomationEngine (incoming_message)
        |
        v
AI_REPLY action
        |
        v
AIService::autoReply()
        |
        +--> Knowledge Base retrieval
        +--> OpenAI Responses API
        +--> confidence / human-handoff decision
        |
        +--> low confidence / handoff -> AI disabled + HIGH priority
        |
        `--> scheduled_messages (2 sec delay)
                    |
                    v
            Scheduled Worker
                    |
                    v
              Baileys Gateway
```

## Enable Agent

```http
GET /api/ai/agent
PUT /api/ai/agent
```

Example:

```json
{
  "enabled": true,
  "auto_reply": true,
  "confidence_threshold": 75,
  "style": "friendly",
  "max_reply_chars": 1500
}
```

## Automation Rule

Create an automation with:

```json
{
  "name": "AI CS WhatsApp",
  "trigger_event": "incoming_message",
  "conditions": {},
  "actions": [
    { "type": "AI_REPLY" }
  ]
}
```

Then enable the rule.

## Safety

- AI only uses tenant-scoped customer conversation and knowledge data.
- AI never sends directly to Baileys; it creates a `scheduled_messages` job.
- Low-confidence replies are not sent.
- Human handoff disables AI for the conversation and raises priority to HIGH.
- Existing opt-out and queue controls remain the final sending boundary.
- Every AI request is logged in `ai_messages` and `ai_usage_logs`.
