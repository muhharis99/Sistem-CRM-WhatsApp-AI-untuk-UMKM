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
}
