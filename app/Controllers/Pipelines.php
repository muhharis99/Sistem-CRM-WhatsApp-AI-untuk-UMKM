<?php
namespace App\Controllers;

class Pipelines extends BaseApiController
{
    public function index()
    {
        $tenantId = $this->tenantId();
        if (!$tenantId) return $this->fail('Unauthenticated.', 401);
        $db = db_connect();
        $pipelines = $db->table('pipelines')->where('tenant_id',$tenantId)->orderBy('id','ASC')->get()->getResultArray();
        foreach ($pipelines as &$pipeline) $pipeline['stages'] = $db->table('pipeline_stages')->where('pipeline_id',$pipeline['id'])->orderBy('position','ASC')->get()->getResultArray();
        return $this->respond(['success'=>true,'data'=>$pipelines]);
    }

    public function create()
    {
        $tenantId = $this->tenantId();
        if (!$tenantId) return $this->fail('Unauthenticated.', 401);
        $data = $this->request->getJSON(true) ?? [];
        $name = trim((string)($data['name'] ?? ''));
        if ($name === '') return $this->fail('Nama pipeline wajib diisi.');
        $db = db_connect();
        $db->transStart();
        $db->table('pipelines')->insert(['tenant_id'=>$tenantId,'name'=>$name,'is_default'=>0,'created_at'=>date('Y-m-d H:i:s'),'updated_at'=>date('Y-m-d H:i:s')]);
        $pipelineId = $db->insertID();
        $stages = $data['stages'] ?? ['NEW','CONTACTED','QUALIFIED','PROPOSAL','NEGOTIATION','WON','LOST'];
        foreach (array_values($stages) as $i => $stage) $db->table('pipeline_stages')->insert(['pipeline_id'=>$pipelineId,'name'=>trim((string)$stage),'position'=>$i,'probability'=>0,'created_at'=>date('Y-m-d H:i:s'),'updated_at'=>date('Y-m-d H:i:s')]);
        $db->transComplete();
        if (!$db->transStatus()) return $this->fail('Gagal membuat pipeline.',500);
        return $this->respondCreated(['success'=>true,'data'=>['id'=>$pipelineId,'name'=>$name,'stages'=>$stages]]);
    }

    public function deals()
    {
        $tenantId = $this->tenantId();
        if (!$tenantId) return $this->fail('Unauthenticated.', 401);
        $rows = db_connect()->table('deals d')->select('d.*, c.name AS customer_name, ps.name AS stage_name')->join('customers c','c.id=d.customer_id','left')->join('pipeline_stages ps','ps.id=d.stage_id')->where('d.tenant_id',$tenantId)->orderBy('d.id','DESC')->get()->getResultArray();
        return $this->respond(['success'=>true,'data'=>$rows]);
    }

    public function createDeal()
    {
        $tenantId = $this->tenantId();
        if (!$tenantId) return $this->fail('Unauthenticated.', 401);
        $data = $this->request->getJSON(true) ?? [];
        foreach (['pipeline_id','stage_id','title'] as $key) if (empty($data[$key])) return $this->fail($key.' wajib diisi.');
        $db = db_connect();
        $valid = $db->table('pipeline_stages ps')->select('ps.id,ps.pipeline_id')->join('pipelines p','p.id=ps.pipeline_id')->where('p.tenant_id',$tenantId)->where('p.id',(int)$data['pipeline_id'])->where('ps.id',(int)$data['stage_id'])->get()->getRowArray();
        if (!$valid) return $this->fail('Pipeline/stage tidak valid.',422);
        $customerId = !empty($data['customer_id']) ? (int)$data['customer_id'] : null;
        if ($customerId && !$db->table('customers')->where('tenant_id',$tenantId)->where('id',$customerId)->countAllResults()) return $this->fail('Customer tidak valid.',422);
        $db->table('deals')->insert(['tenant_id'=>$tenantId,'pipeline_id'=>(int)$data['pipeline_id'],'stage_id'=>(int)$data['stage_id'],'customer_id'=>$customerId,'owner_id'=>$data['owner_id'] ?? null,'title'=>trim((string)$data['title']),'value'=>(float)($data['value'] ?? 0),'currency'=>strtoupper((string)($data['currency'] ?? 'IDR')),'status'=>'OPEN','expected_close_date'=>$data['expected_close_date'] ?? null,'notes'=>$data['notes'] ?? null,'created_at'=>date('Y-m-d H:i:s'),'updated_at'=>date('Y-m-d H:i:s')]);
        return $this->respondCreated(['success'=>true,'data'=>$db->table('deals')->where('id',$db->insertID())->get()->getRowArray()]);
    }
}
