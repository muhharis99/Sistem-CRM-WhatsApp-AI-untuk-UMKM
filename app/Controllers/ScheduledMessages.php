<?php
namespace App\Controllers;
use App\Models\ScheduledMessageModel;
use App\Models\CustomerModel;
use App\Models\MessageTemplateModel;
use App\Services\TemplateRendererService;

class ScheduledMessages extends BaseApiController
{
    public function index(){ $t=$this->tenantId(); if(!$t)return $this->fail('Unauthenticated.',401); return $this->respond(['success'=>true,'data'=>(new ScheduledMessageModel())->where('tenant_id',$t)->orderBy('scheduled_at','ASC')->findAll()]); }
    public function create(){ $t=$this->tenantId(); if(!$t)return $this->fail('Unauthenticated.',401); $d=$this->request->getJSON(true)??[]; $customer=(new CustomerModel())->where('tenant_id',$t)->find((int)($d['customer_id']??0)); if(!$customer)return $this->fail('Customer tidak ditemukan.',404); $body=trim((string)($d['body']??'')); if(!empty($d['template_id'])){ $tpl=(new MessageTemplateModel())->where('tenant_id',$t)->find((int)$d['template_id']); if(!$tpl)return $this->fail('Template tidak ditemukan.',404); $body=(new TemplateRendererService())->render($tpl['body'],$d['variables']??[]); } if($body===''||empty($d['scheduled_at']))return $this->fail('Pesan dan waktu jadwal wajib diisi.'); $m=new ScheduledMessageModel(); $id=$m->insert(['tenant_id'=>$t,'customer_id'=>$customer['id'],'device_id'=>$d['device_id']??null,'template_id'=>$d['template_id']??null,'body'=>$body,'scheduled_at'=>$d['scheduled_at'],'timezone'=>$d['timezone']??'Asia/Jakarta','status'=>'PENDING','attempts'=>0,'max_attempts'=>min(10,max(1,(int)($d['max_attempts']??3))),'created_by'=>session('user_id')],true); return $this->respondCreated(['success'=>true,'data'=>$m->find($id)]); }

    public function claimDue(){
        if(!$this->validInternalSecret())return $this->fail('Unauthorized.',401);
        $limit=min(100,max(1,(int)(($this->request->getJSON(true)??[])['limit']??25)));$db=db_connect();$items=[];$db->transStart();
        $rows=$db->query("SELECT sm.*,c.phone,c.name FROM scheduled_messages sm INNER JOIN customers c ON c.id=sm.customer_id AND c.tenant_id=sm.tenant_id WHERE sm.status='PENDING' AND sm.scheduled_at<=NOW() ORDER BY sm.scheduled_at ASC LIMIT {$limit} FOR UPDATE")->getResultArray();
        foreach($rows as $row){$deviceId=(int)($row['device_id']??0);if(!$deviceId){$device=$db->table('whatsapp_devices')->where('tenant_id',(int)$row['tenant_id'])->whereIn('status',['CONNECTED','READY'])->orderBy('id','ASC')->get(1)->getRowArray();$deviceId=(int)($device['id']??0);}if(!$deviceId){$db->table('scheduled_messages')->where('id',(int)$row['id'])->update(['status'=>'FAILED','attempts'=>(int)$row['attempts']+1,'last_error'=>'Tidak ada WhatsApp device aktif','updated_at'=>date('Y-m-d H:i:s')]);continue;}$db->table('scheduled_messages')->where('id',(int)$row['id'])->where('status','PENDING')->update(['status'=>'PROCESSING','device_id'=>$deviceId,'attempts'=>(int)$row['attempts']+1,'updated_at'=>date('Y-m-d H:i:s')]);$row['status']='PROCESSING';$row['device_id']=$deviceId;$row['attempts']=(int)$row['attempts']+1;$items[]=$row;}$db->transComplete();return $this->respond(['success'=>true,'data'=>$items]);
    }

    public function complete(int $id){
        if(!$this->validInternalSecret())return $this->fail('Unauthorized.',401);$p=$this->request->getJSON(true)??[];$m=new ScheduledMessageModel();$row=$m->find($id);if(!$row)return $this->fail('Scheduled message tidak ditemukan.',404);$status=(string)($p['status']??'FAILED');$updates=['status'=>$status,'message_id'=>$p['message_id']??null,'last_error'=>$p['error_message']??null,'updated_at'=>date('Y-m-d H:i:s')];if($status==='SENT')$updates['sent_at']=date('Y-m-d H:i:s');if($status==='PENDING')$updates['scheduled_at']=date('Y-m-d H:i:s',time()+max(10,(int)(pow(2,max(0,(int)($p['attempts']??$row['attempts'])-1))*5)));$m->update($id,$updates);return $this->respond(['success'=>true,'data'=>$m->find($id)]);
    }

    private function validInternalSecret(): bool{ $expected=(string)env('QUEUE_INTERNAL_SECRET','');$provided=(string)$this->request->getHeaderLine('X-Queue-Internal-Secret');return $expected!==''&&hash_equals($expected,$provided); }
}
