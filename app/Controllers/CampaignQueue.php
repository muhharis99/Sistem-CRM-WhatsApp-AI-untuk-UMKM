<?php
namespace App\Controllers;

use App\Models\CampaignModel;
use App\Services\BroadcastQueueService;

class CampaignQueue extends BaseApiController
{
    public function enqueue(int $id)
    {
        $tenantId = $this->tenantId();
        if (!$tenantId) return $this->fail('Unauthenticated.', 401);
        try {
            return $this->respond(['success'=>true,'data'=>(new BroadcastQueueService())->enqueueCampaign($tenantId,$id)]);
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage(), 422);
        }
    }

    public function claim(int $id)
    {
        if (!$this->validInternalSecret()) return $this->fail('Unauthorized.', 401);
        $payload = $this->request->getJSON(true) ?? [];
        $tenantId = (int)($payload['tenant_id'] ?? 0);
        if ($tenantId < 1) return $this->fail('tenant_id wajib diisi.', 422);
        try {
            return $this->respond(['success'=>true,'data'=>(new BroadcastQueueService())->claim($tenantId,$id)]);
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage(), 422);
        }
    }

    public function complete(int $id)
    {
        if (!$this->validInternalSecret()) return $this->fail('Unauthorized.', 401);
        $payload = $this->request->getJSON(true) ?? [];
        $tenantId = (int)($payload['tenant_id'] ?? 0);
        if ($tenantId < 1) return $this->fail('tenant_id wajib diisi.', 422);
        try {
            $data = (new BroadcastQueueService())->complete($tenantId,$id,(string)($payload['status'] ?? 'FAILED'),$payload['message_id']??null,$payload['error_message']??null,isset($payload['attempts'])?(int)$payload['attempts']:null);
            return $this->respond(['success'=>true,'data'=>$data]);
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage(), 422);
        }
    }

    public function show(int $id)
    {
        $tenantId = $this->tenantId();
        if (!$tenantId) return $this->fail('Unauthenticated.',401);
        $campaign = (new CampaignModel())->where('tenant_id',$tenantId)->find($id);
        if (!$campaign) return $this->fail('Campaign tidak ditemukan.',404);
        $db = db_connect();
        $stats = $db->query("SELECT status,COUNT(*) total FROM campaign_recipients WHERE tenant_id=? AND campaign_id=? GROUP BY status",[$tenantId,$id])->getResultArray();
        return $this->respond(['success'=>true,'data'=>['campaign'=>$campaign,'recipient_stats'=>$stats]]);
    }

    private function validInternalSecret(): bool
    {
        $expected = (string) env('QUEUE_INTERNAL_SECRET','');
        $provided = (string) $this->request->getHeaderLine('X-Queue-Internal-Secret');
        return $expected !== '' && hash_equals($expected,$provided);
    }
}
