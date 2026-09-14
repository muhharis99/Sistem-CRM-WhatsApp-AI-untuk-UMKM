<?php
namespace App\Controllers;

use CodeIgniter\Database\BaseConnection;

class CustomerWorkspace extends BaseApiController
{
    private function customer(int $id): ?array
    {
        $tenantId = $this->tenantId();
        if (!$tenantId) return null;
        return db_connect()->table('customers')->where('tenant_id', $tenantId)->where('id', $id)->get()->getRowArray() ?: null;
    }

    public function show(int $id)
    {
        $tenantId = $this->tenantId();
        if (!$tenantId) return $this->fail('Unauthenticated.', 401);
        $customer = $this->customer($id);
        if (!$customer) return $this->fail('Customer tidak ditemukan.', 404);
        $db = db_connect();

        $tags = $db->table('customer_tags ct')
            ->select('t.id,t.name,t.color')
            ->join('tags t', 't.id=ct.tag_id')
            ->where('t.tenant_id', $tenantId)->where('ct.customer_id', $id)
            ->orderBy('t.name', 'ASC')->get()->getResultArray();

        $notes = $db->table('customer_notes n')
            ->select('n.*,u.name AS user_name')->join('users u','u.id=n.user_id','left')
            ->where('n.tenant_id',$tenantId)->where('n.customer_id',$id)
            ->orderBy('n.id','DESC')->get(50)->getResultArray();

        $conversations = $db->table('conversations c')
            ->select('c.*,d.name AS device_name,u.name AS assigned_name')
            ->join('whatsapp_devices d','d.id=c.device_id','left')->join('users u','u.id=c.assigned_to','left')
            ->where('c.tenant_id',$tenantId)->where('c.customer_id',$id)
            ->orderBy('c.last_message_at','DESC')->get(25)->getResultArray();

        $deals = $db->table('deals d')
            ->select('d.*,p.name AS pipeline_name,ps.name AS stage_name,ps.position AS stage_position,ps.probability,u.name AS owner_name')
            ->join('pipelines p','p.id=d.pipeline_id')->join('pipeline_stages ps','ps.id=d.stage_id')
            ->join('users u','u.id=d.owner_id','left')
            ->where('d.tenant_id',$tenantId)->where('d.customer_id',$id)
            ->orderBy('d.id','DESC')->get(50)->getResultArray();

        $tasks = $db->table('tasks t')
            ->select('t.*,u.name AS assigned_name,d.title AS deal_title')
            ->join('users u','u.id=t.assigned_to','left')->join('deals d','d.id=t.deal_id','left')
            ->where('t.tenant_id',$tenantId)->where('t.customer_id',$id)
            ->orderBy('t.status','ASC')->orderBy('t.due_at','ASC')->get(50)->getResultArray();

        $activities = $db->table('activities a')
            ->select('a.*,u.name AS user_name')
            ->join('users u','u.id=a.user_id','left')
            ->where('a.tenant_id',$tenantId)->where('a.customer_id',$id)
            ->orderBy('a.created_at','DESC')->get(100)->getResultArray();

        $conversationCount = count($conversations);
        $messageCount = (int)$db->table('messages')->where('tenant_id',$tenantId)->where('conversation_id IN (SELECT id FROM conversations WHERE tenant_id='.$db->escape($tenantId).' AND customer_id='.$db->escape($id).')', null, false)->countAllResults();
        $openDealsValue = 0.0;
        $wonDealsValue = 0.0;
        foreach ($deals as $deal) {
            if ($deal['status'] === 'OPEN') $openDealsValue += (float)$deal['value'];
            if ($deal['status'] === 'WON') $wonDealsValue += (float)$deal['value'];
        }

        return $this->respond([
            'success' => true,
            'data' => [
                'customer' => $customer,
                'tags' => $tags,
                'notes' => $notes,
                'conversations' => $conversations,
                'deals' => $deals,
                'tasks' => $tasks,
                'activities' => $activities,
                'stats' => [
                    'conversations' => $conversationCount,
                    'messages' => $messageCount,
                    'open_deals' => count(array_filter($deals, static fn($d) => $d['status'] === 'OPEN')),
                    'open_deals_value' => $openDealsValue,
                    'won_deals_value' => $wonDealsValue,
                    'activities' => count($activities),
                ],
            ],
        ]);
    }

    public function addNote(int $customerId)
    {
        $tenantId = $this->tenantId();
        if (!$tenantId) return $this->fail('Unauthenticated.', 401);
        if (!$this->customer($customerId)) return $this->fail('Customer tidak ditemukan.',404);
        $data = $this->request->getJSON(true) ?? [];
        $body = trim((string)($data['body'] ?? ''));
        if ($body === '') return $this->fail('Catatan wajib diisi.');
        $now = date('Y-m-d H:i:s');
        $db = db_connect();
        $db->table('customer_notes')->insert(['tenant_id'=>$tenantId,'customer_id'=>$customerId,'user_id'=>session('user_id') ?: null,'body'=>$body,'created_at'=>$now,'updated_at'=>$now]);
        $id = $db->insertID();
        $db->table('activities')->insert(['tenant_id'=>$tenantId,'customer_id'=>$customerId,'user_id'=>session('user_id') ?: null,'type'=>'NOTE','title'=>'Customer note ditambahkan','body'=>$body,'created_at'=>$now]);
        return $this->respondCreated(['success'=>true,'data'=>$db->table('customer_notes')->where('id',$id)->where('tenant_id',$tenantId)->get()->getRowArray()]);
    }

    public function deleteNote(int $customerId, int $noteId)
    {
        $tenantId = $this->tenantId();
        if (!$tenantId) return $this->fail('Unauthenticated.',401);
        $db = db_connect();
        $note = $db->table('customer_notes')->where('tenant_id',$tenantId)->where('customer_id',$customerId)->where('id',$noteId)->get()->getRowArray();
        if (!$note) return $this->fail('Catatan tidak ditemukan.',404);
        $db->table('customer_notes')->where('id',$noteId)->delete();
        return $this->respond(['success'=>true]);
    }

    public function addTask(int $customerId)
    {
        $tenantId = $this->tenantId();
        if (!$tenantId) return $this->fail('Unauthenticated.',401);
        if (!$this->customer($customerId)) return $this->fail('Customer tidak ditemukan.',404);
        $data = $this->request->getJSON(true) ?? [];
        $title = trim((string)($data['title'] ?? ''));
        if ($title === '') return $this->fail('Judul task wajib diisi.');
        $allowedPriority=['LOW','NORMAL','HIGH','URGENT'];
        $priority=in_array(($data['priority']??'NORMAL'),$allowedPriority,true)?$data['priority']:'NORMAL';
        $db=db_connect();
        if (!empty($data['deal_id']) && !$db->table('deals')->where('tenant_id',$tenantId)->where('customer_id',$customerId)->where('id',(int)$data['deal_id'])->countAllResults()) return $this->fail('Deal tidak valid.',422);
        $now=date('Y-m-d H:i:s');
        $db->table('tasks')->insert(['tenant_id'=>$tenantId,'customer_id'=>$customerId,'deal_id'=>$data['deal_id']??null,'assigned_to'=>$data['assigned_to']??session('user_id') ?: null,'title'=>$title,'description'=>trim((string)($data['description']??''))?:null,'due_at'=>$data['due_at']??null,'status'=>'OPEN','priority'=>$priority,'created_at'=>$now,'updated_at'=>$now]);
        $taskId=$db->insertID();
        $db->table('activities')->insert(['tenant_id'=>$tenantId,'customer_id'=>$customerId,'user_id'=>session('user_id') ?: null,'type'=>'TASK','title'=>'Task dibuat','body'=>$title,'created_at'=>$now]);
        return $this->respondCreated(['success'=>true,'data'=>$db->table('tasks')->where('id',$taskId)->get()->getRowArray()]);
    }

    public function updateTask(int $customerId, int $taskId)
    {
        $tenantId=$this->tenantId();
        if(!$tenantId)return $this->fail('Unauthenticated.',401);
        $db=db_connect();
        if(!$db->table('tasks')->where('tenant_id',$tenantId)->where('customer_id',$customerId)->where('id',$taskId)->countAllResults())return $this->fail('Task tidak ditemukan.',404);
        $data=$this->request->getJSON(true)??[];
        $update=[];
        if(isset($data['status']) && in_array($data['status'],['OPEN','IN_PROGRESS','DONE','CANCELLED'],true))$update['status']=$data['status'];
        if(isset($data['priority']) && in_array($data['priority'],['LOW','NORMAL','HIGH','URGENT'],true))$update['priority']=$data['priority'];
        if(array_key_exists('due_at',$data))$update['due_at']=$data['due_at']?:null;
        if(array_key_exists('assigned_to',$data))$update['assigned_to']=$data['assigned_to']?:null;
        if(!$update)return $this->fail('Tidak ada perubahan.');
        $update['updated_at']=date('Y-m-d H:i:s');
        $db->table('tasks')->where('tenant_id',$tenantId)->where('customer_id',$customerId)->where('id',$taskId)->update($update);
        return $this->respond(['success'=>true,'data'=>$db->table('tasks')->where('id',$taskId)->get()->getRowArray()]);
    }

    public function createDeal(int $customerId)
    {
        $tenantId=$this->tenantId();
        if(!$tenantId)return $this->fail('Unauthenticated.',401);
        if(!$this->customer($customerId))return $this->fail('Customer tidak ditemukan.',404);
        $data=$this->request->getJSON(true)??[];
        foreach(['pipeline_id','stage_id','title'] as $key)if(empty($data[$key]))return $this->fail($key.' wajib diisi.');
        $db=db_connect();
        $valid=$db->table('pipeline_stages ps')->select('ps.id,ps.pipeline_id')->join('pipelines p','p.id=ps.pipeline_id')->where('p.tenant_id',$tenantId)->where('p.id',(int)$data['pipeline_id'])->where('ps.id',(int)$data['stage_id'])->get()->getRowArray();
        if(!$valid)return $this->fail('Pipeline/stage tidak valid.',422);
        $now=date('Y-m-d H:i:s');
        $db->table('deals')->insert(['tenant_id'=>$tenantId,'pipeline_id'=>(int)$data['pipeline_id'],'stage_id'=>(int)$data['stage_id'],'customer_id'=>$customerId,'owner_id'=>$data['owner_id']??session('user_id')?:null,'title'=>trim((string)$data['title']),'value'=>(float)($data['value']??0),'currency'=>strtoupper((string)($data['currency']??'IDR')),'status'=>'OPEN','expected_close_date'=>$data['expected_close_date']??null,'notes'=>$data['notes']??null,'created_at'=>$now,'updated_at'=>$now]);
        $dealId=$db->insertID();
        $db->table('activities')->insert(['tenant_id'=>$tenantId,'customer_id'=>$customerId,'user_id'=>session('user_id') ?: null,'type'=>'DEAL','title'=>'Deal dibuat','body'=>trim((string)$data['title']),'created_at'=>$now]);
        return $this->respondCreated(['success'=>true,'data'=>$db->table('deals')->where('id',$dealId)->get()->getRowArray()]);
    }

    public function updateDeal(int $customerId, int $dealId)
    {
        $tenantId=$this->tenantId();
        if(!$tenantId)return $this->fail('Unauthenticated.',401);
        $db=db_connect();
        $deal=$db->table('deals')->where('tenant_id',$tenantId)->where('customer_id',$customerId)->where('id',$dealId)->get()->getRowArray();
        if(!$deal)return $this->fail('Deal tidak ditemukan.',404);
        $data=$this->request->getJSON(true)??[];$update=[];
        if(isset($data['stage_id'])){
            $valid=$db->table('pipeline_stages ps')->join('pipelines p','p.id=ps.pipeline_id')->where('p.tenant_id',$tenantId)->where('p.id',$deal['pipeline_id'])->where('ps.id',(int)$data['stage_id'])->get()->getRowArray();
            if(!$valid)return $this->fail('Stage tidak valid.',422);$update['stage_id']=(int)$data['stage_id'];
        }
        if(isset($data['status']) && in_array($data['status'],['OPEN','WON','LOST'],true))$update['status']=$data['status'];
        if(array_key_exists('value',$data))$update['value']=(float)$data['value'];
        if(array_key_exists('expected_close_date',$data))$update['expected_close_date']=$data['expected_close_date']?:null;
        if(array_key_exists('owner_id',$data))$update['owner_id']=$data['owner_id']?:null;
        if(!$update)return $this->fail('Tidak ada perubahan.');
        $update['updated_at']=date('Y-m-d H:i:s');$db->table('deals')->where('tenant_id',$tenantId)->where('customer_id',$customerId)->where('id',$dealId)->update($update);
        if(isset($update['stage_id']) || isset($update['status'])){
            $stageName=$db->table('pipeline_stages')->where('id',$update['stage_id']??$deal['stage_id'])->get()->getRowArray()['name']??null;
            $db->table('activities')->insert(['tenant_id'=>$tenantId,'customer_id'=>$customerId,'user_id'=>session('user_id') ?: null,'type'=>'DEAL_UPDATE','title'=>'Deal diperbarui','body'=>trim(($stageName?'Stage: '.$stageName.' ':'').(isset($update['status'])?'Status: '.$update['status']:'')),'created_at'=>date('Y-m-d H:i:s')]);
        }
        return $this->respond(['success'=>true,'data'=>$db->table('deals')->where('id',$dealId)->get()->getRowArray()]);
    }
}
