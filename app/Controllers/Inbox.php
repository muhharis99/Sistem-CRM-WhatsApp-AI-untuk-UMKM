<?php
namespace App\Controllers;

use App\Models\ConversationModel;
use App\Models\MessageModel;
use App\Services\WhatsAppGatewayService;

class Inbox extends BaseApiController
{
    public function conversations()
    {
        $tenantId = $this->tenantId();
        if (!$tenantId) return $this->fail('Unauthenticated.', 401);
        $db = db_connect();
        $builder = $db->table('conversations c')
            ->select('c.*, cu.name AS customer_name, cu.phone AS customer_phone, u.name AS assigned_name')
            ->join('customers cu', 'cu.id = c.customer_id')
            ->join('users u', 'u.id = c.assigned_to', 'left')
            ->where('c.tenant_id', $tenantId)
            ->orderBy('c.last_message_at', 'DESC');
        $status = $this->request->getGet('status');
        $search = trim((string) $this->request->getGet('search'));
        if ($status && in_array($status, ['OPEN','PENDING','RESOLVED','ARCHIVED'], true)) $builder->where('c.status', $status);
        if ($search !== '') $builder->groupStart()->like('cu.name', $search)->orLike('cu.phone', $search)->groupEnd();
        return $this->respond(['success' => true, 'data' => $builder->get(100)->getResultArray()]);
    }

    public function messages(int $conversationId)
    {
        $tenantId = $this->tenantId();
        if (!$tenantId) return $this->fail('Unauthenticated.', 401);
        $db = db_connect();
        $conv = $db->table('conversations c')->select('c.*, cu.name AS customer_name, cu.phone AS customer_phone')
            ->join('customers cu', 'cu.id = c.customer_id')->where('c.tenant_id', $tenantId)->where('c.id', $conversationId)->get()->getRowArray();
        if (!$conv) return $this->fail('Conversation tidak ditemukan.', 404);
        $rows = (new MessageModel())->where('tenant_id', $tenantId)->where('conversation_id', $conversationId)->orderBy('timestamp', 'ASC')->findAll(300);
        return $this->respond(['success' => true, 'conversation' => $conv, 'data' => $rows]);
    }

    public function realtimeToken()
    {
        $tenantId = $this->tenantId();
        if (!$tenantId) return $this->fail('Unauthenticated.', 401);
        $secret = (string) env('WHATSAPP_GATEWAY_SECRET', '');
        if ($secret === '') return $this->fail('Realtime gateway secret belum dikonfigurasi.', 500);
        $payload = rtrim(strtr(base64_encode(json_encode(['tenant_id' => $tenantId, 'exp' => time() + 300], JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
        $signature = hash_hmac('sha256', $payload, $secret);
        return $this->respond(['success' => true, 'data' => ['tenant_id' => $tenantId, 'token' => $payload . '.' . $signature, 'expires_in' => 300]]);
    }

    public function markRead(int $conversationId)
    {
        $tenantId = $this->tenantId();
        if (!$tenantId) return $this->fail('Unauthenticated.', 401);
        $model = new ConversationModel();
        $conv = $model->where('tenant_id', $tenantId)->find($conversationId);
        if (!$conv) return $this->fail('Conversation tidak ditemukan.', 404);
        $model->update($conversationId, ['unread_count' => 0]);
        return $this->respond(['success' => true]);
    }

    public function updateConversation(int $conversationId)
    {
        $tenantId = $this->tenantId();
        if (!$tenantId) return $this->fail('Unauthenticated.', 401);
        $model = new ConversationModel();
        $conv = $model->where('tenant_id', $tenantId)->find($conversationId);
        if (!$conv) return $this->fail('Conversation tidak ditemukan.', 404);
        $data = $this->request->getJSON(true) ?? [];
        $allowed = ['status','assigned_to','ai_enabled','priority'];
        $update = array_intersect_key($data, array_flip($allowed));
        if (isset($update['status']) && !in_array($update['status'], ['OPEN','PENDING','RESOLVED','ARCHIVED'], true)) return $this->fail('Status conversation tidak valid.');
        if (isset($update['priority']) && !in_array($update['priority'], ['LOW','NORMAL','HIGH','URGENT'], true)) return $this->fail('Priority tidak valid.');
        $model->update($conversationId, $update);
        return $this->respond(['success' => true, 'data' => $model->where('tenant_id', $tenantId)->find($conversationId)]);
    }

    public function send(int $conversationId)
    {
        $tenantId = $this->tenantId();
        if (!$tenantId) return $this->fail('Unauthenticated.', 401);
        $convModel = new ConversationModel();
        $conv = $convModel->where('tenant_id', $tenantId)->find($conversationId);
        if (!$conv) return $this->fail('Conversation tidak ditemukan.', 404);
        $data = $this->request->getJSON(true) ?? [];
        $text = trim((string)($data['text'] ?? ''));
        if ($text === '') return $this->fail('Pesan wajib diisi.');
        $db = db_connect();
        $customer = $db->table('customers')->where('id', $conv['customer_id'])->where('tenant_id', $tenantId)->get()->getRowArray();
        if (!$customer) return $this->fail('Customer tidak ditemukan.', 404);
        if (!$conv['device_id']) return $this->fail('Conversation belum memiliki WhatsApp device.');
        $device = $db->table('whatsapp_devices')->where('id', $conv['device_id'])->where('tenant_id', $tenantId)->get()->getRowArray();
        if (!$device) return $this->fail('WhatsApp device tidak ditemukan.', 404);
        $jid = str_ends_with($customer['phone'], '@s.whatsapp.net') ? $customer['phone'] : $customer['phone'].'@s.whatsapp.net';
        try {
            $gateway = (new WhatsAppGatewayService())->sendText($tenantId, (int)$device['id'], $jid, $text);
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage(), 502);
        }
        $messageId = (string)($gateway['message']['key']['id'] ?? ('crm-'.bin2hex(random_bytes(8))));
        $now = date('Y-m-d H:i:s');
        (new MessageModel())->insert(['tenant_id' => $tenantId, 'conversation_id' => $conversationId, 'device_id' => $device['id'], 'message_id' => $messageId, 'remote_jid' => $jid, 'sender_type' => 'AGENT', 'message_type' => 'text', 'body' => $text, 'status' => 'SENT', 'direction' => 'OUTGOING', 'timestamp' => $now]);
        $convModel->update($conversationId, ['last_message_at' => $now, 'unread_count' => 0]);
        return $this->respond(['success' => true, 'data' => ['message_id' => $messageId, 'body' => $text, 'conversation_id' => $conversationId]]);
    }
}
