<?php
namespace App\Controllers;

use App\Services\AI\AIService;

class AI extends BaseApiController
{
    public function suggest(int $conversationId)
    {
        $tenantId = $this->tenantId();
        if (!$tenantId) return $this->fail('Unauthenticated.',401);
        $data = $this->request->getJSON(true) ?? [];
        try {
            return $this->respond(['success'=>true,'data'=>(new AIService())->suggestReply($tenantId,$conversationId,(string)($data['style'] ?? 'friendly'))]);
        } catch (\Throwable $e) { return $this->fail($e->getMessage(),502); }
    }

    public function analyze(int $conversationId)
    {
        $tenantId = $this->tenantId();
        if (!$tenantId) return $this->fail('Unauthenticated.',401);
        try {
            return $this->respond(['success'=>true,'data'=>(new AIService())->analyzeConversation($tenantId,$conversationId)]);
        } catch (\Throwable $e) { return $this->fail($e->getMessage(),502); }
    }

    public function knowledge()
    {
        $tenantId = $this->tenantId();
        if (!$tenantId) return $this->fail('Unauthenticated.',401);
        $db = db_connect();
        $method = $this->request->getMethod();
        if ($method === 'get') {
            return $this->respond(['success'=>true,'data'=>$db->table('knowledge_documents')->where('tenant_id',$tenantId)->orderBy('id','DESC')->get()->getResultArray()]);
        }
        $data = $this->request->getJSON(true) ?? [];
        $title = trim((string)($data['title'] ?? '')); $content = trim((string)($data['content'] ?? ''));
        if ($title==='' || $content==='') return $this->fail('Title dan content wajib diisi.');
        $kb = $db->table('knowledge_bases')->where('tenant_id',$tenantId)->get()->getFirstRow('array');
        if (!$kb) { $db->table('knowledge_bases')->insert(['tenant_id'=>$tenantId,'name'=>'Default Knowledge','description'=>'Knowledge base utama','active'=>1]); $kbId=$db->insertID(); } else $kbId=$kb['id'];
        $db->table('knowledge_documents')->insert(['tenant_id'=>$tenantId,'knowledge_base_id'=>$kbId,'title'=>$title,'content'=>$content,'source'=>$data['source'] ?? 'MANUAL','active'=>1]);
        return $this->respondCreated(['success'=>true]);
    }

    public function toggleHandoff(int $conversationId)
    {
        $tenantId = $this->tenantId(); if (!$tenantId) return $this->fail('Unauthenticated.',401);
        $enabled = (bool)(($this->request->getJSON(true) ?? [])['ai_enabled'] ?? true);
        $updated = db_connect()->table('conversations')->where('tenant_id',$tenantId)->where('id',$conversationId)->update(['ai_enabled'=>$enabled ? 1 : 0]);
        if (!$updated) return $this->fail('Conversation tidak ditemukan.',404);
        return $this->respond(['success'=>true,'ai_enabled'=>$enabled]);
    }
}
