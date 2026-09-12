<?php
namespace App\Controllers;

use App\Models\ConversationModel;
use App\Models\MessageModel;

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
        if ($status && in_array($status, ['OPEN','PENDING','RESOLVED','ARCHIVED'], true)) $builder->where('c.status', $status);
        return $this->respond(['success' => true, 'data' => $builder->get(50)->getResultArray()]);
    }

    public function messages(int $conversationId)
    {
        $tenantId = $this->tenantId();
        if (!$tenantId) return $this->fail('Unauthenticated.', 401);
        $conv = (new ConversationModel())->where('tenant_id', $tenantId)->find($conversationId);
        if (!$conv) return $this->fail('Conversation tidak ditemukan.', 404);
        $model = new MessageModel();
        $rows = $model->where('tenant_id', $tenantId)->where('conversation_id', $conversationId)->orderBy('timestamp', 'ASC')->findAll(200);
        return $this->respond(['success' => true, 'conversation' => $conv, 'data' => $rows]);
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
        return $this->respond(['success' => true, 'data' => $model->find($conversationId)]);
    }
}
