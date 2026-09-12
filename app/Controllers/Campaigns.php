<?php
namespace App\Controllers;
use App\Models\CampaignModel;
use App\Models\MessageTemplateModel;
use App\Services\TemplateRendererService;

class Campaigns extends BaseApiController
{
    public function index(){ $t=$this->tenantId(); if(!$t)return $this->fail('Unauthenticated.',401); return $this->respond(['success'=>true,'data'=>(new CampaignModel())->where('tenant_id',$t)->orderBy('id','DESC')->findAll()]); }
    public function create(){ $t=$this->tenantId(); if(!$t)return $this->fail('Unauthenticated.',401); $d=$this->request->getJSON(true)??[]; $name=trim((string)($d['name']??'')); if($name==='')return $this->fail('Nama campaign wajib diisi.'); $body=trim((string)($d['message_body']??'')); if(!$body && !empty($d['template_id'])){ $tpl=(new MessageTemplateModel())->where('tenant_id',$t)->find((int)$d['template_id']); if(!$tpl)return $this->fail('Template tidak ditemukan.',404); $body=$tpl['body']; } if($body==='')return $this->fail('Isi pesan atau template wajib diisi.'); $m=new CampaignModel(); $id=$m->insert(['tenant_id'=>$t,'name'=>$name,'template_id'=>$d['template_id']??null,'message_body'=>$body,'segment_json'=>json_encode($d['segment']??[]),'scheduled_at'=>$d['scheduled_at']??null,'timezone'=>$d['timezone']??'Asia/Jakarta','status'=>'DRAFT','rate_limit_per_minute'=>max(1,(int)($d['rate_limit_per_minute']??20)),'created_by'=>session('user_id')],true); return $this->respondCreated(['success'=>true,'data'=>$m->find($id)]); }
    public function preview(int $id){ $t=$this->tenantId(); if(!$t)return $this->fail('Unauthenticated.',401); $m=new CampaignModel(); $c=$m->where('tenant_id',$t)->find($id); if(!$c)return $this->fail('Campaign tidak ditemukan.',404); $vars=$this->request->getJSON(true)['variables']??[]; return $this->respond(['success'=>true,'data'=>['body'=>(new TemplateRendererService())->render($c['message_body']??'', $vars)]]); }
}
