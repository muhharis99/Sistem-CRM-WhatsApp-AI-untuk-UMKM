<?php
namespace App\Services;

use App\Models\CustomerModel;
use App\Models\ConversationModel;
use App\Models\MessageModel;

class CrmMessageService
{
    public function ingestIncoming(array $payload): ?array
    {
        $tenantId = (int)($payload['tenant_id'] ?? 0);
        $deviceId = (int)($payload['device_id'] ?? 0);
        $messageId = trim((string)($payload['message_id'] ?? ''));
        $phone = trim((string)($payload['phone'] ?? ''));
        $remoteJid = trim((string)($payload['remote_jid'] ?? ''));
        if (!$tenantId || !$deviceId || !$messageId || !$phone || !$remoteJid) return null;

        $messages = new MessageModel();
        $existing = $messages->where('tenant_id', $tenantId)->where('device_id', $deviceId)->where('message_id', $messageId)->first();
        if ($existing) return $existing;

        $customers = new CustomerModel();
        $customer = $customers->where('tenant_id', $tenantId)->where('phone', $phone)->first();
        if (!$customer) {
            $customerId = $customers->insert([
                'tenant_id' => $tenantId,
                'phone' => $phone,
                'name' => $payload['push_name'] ?? null,
                'source' => 'WHATSAPP',
                'status' => 'ACTIVE',
            ], true);
            $customer = $customers->find($customerId);
        } elseif (empty($customer['name']) && !empty($payload['push_name'])) {
            $customers->update($customer['id'], ['name' => $payload['push_name']]);
        }

        $conversations = new ConversationModel();
        $conversation = $conversations->where('tenant_id', $tenantId)
            ->where('device_id', $deviceId)
            ->where('customer_id', $customer['id'])
            ->whereIn('status', ['OPEN','PENDING'])
            ->orderBy('id', 'DESC')->first();
        if (!$conversation) {
            $conversationId = $conversations->insert([
                'tenant_id' => $tenantId,
                'device_id' => $deviceId,
                'customer_id' => $customer['id'],
                'status' => 'OPEN',
                'unread_count' => 0,
                'ai_enabled' => 1,
                'priority' => 'NORMAL',
            ], true);
            $conversation = $conversations->find($conversationId);
        }

        $messageData = [
            'tenant_id' => $tenantId,
            'conversation_id' => $conversation['id'],
            'device_id' => $deviceId,
            'message_id' => $messageId,
            'remote_jid' => $remoteJid,
            'sender_type' => 'CUSTOMER',
            'sender_phone' => $phone,
            'message_type' => $payload['type'] ?? 'text',
            'body' => $payload['body'] ?? null,
            'media_url' => $payload['media_url'] ?? null,
            'media_mime' => $payload['media_mime'] ?? null,
            'quoted_message_id' => $payload['quoted_message_id'] ?? null,
            'status' => 'SENT',
            'direction' => 'INCOMING',
            'timestamp' => $payload['timestamp'] ?? date('Y-m-d H:i:s'),
        ];
        try {
            $messages->insert($messageData);
        } catch (\Throwable $e) {
            $existing = $messages->where('tenant_id', $tenantId)->where('device_id', $deviceId)->where('message_id', $messageId)->first();
            if ($existing) return $existing;
            throw $e;
        }

        $conversations->update($conversation['id'], [
            'last_message_at' => $messageData['timestamp'],
            'unread_count' => ((int)$conversation['unread_count']) + 1,
            'status' => 'OPEN',
        ]);
        return array_merge($messageData, ['id' => $messages->getInsertID(), 'customer_id' => $customer['id'], 'conversation_id' => $conversation['id']]);
    }
}
