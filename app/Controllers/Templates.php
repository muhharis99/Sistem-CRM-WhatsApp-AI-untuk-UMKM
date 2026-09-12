<?php
namespace App\Controllers;
use App\Models\MessageTemplateModel;
use App\Services\TemplateRendererService;

class Templates extends BaseApiController
{
    public function index(){ $tenant=$this->tenantId(); if(!$tenant)return $this->fail('Unauthenticated.',401); return $this->respond(['success'=>true,'data'=>(new MessageTemplateModel())->where('tenant_id',$tenant)->orderBy('id','DESC')->findAll()]); }
    public function create(){ $tenant=$this->tenantId(); if(!$tenant)return $this->fail('Unauthenticated.',401); $d=$this->request->getJSON(true)??[]; $body=trim((string)($d['body']??'')); $name=trim((string)($d['name']??'')); if($name===''||$body==='')return $this->fail('Nama dan isi template wajib diisi.'); $renderer=new TemplateRendererService(); $m=new MessageTemplateModel(); $id=$m->insert(['tenant_id'=>$tenant,'name'=>$name,'category'=>$d['category']??null,'body'=>$body,'variables_json'=>json_encode($renderer->variables($body)),'active'=>(int)($d['active']??1)],true); return $this->respondCreated(['success'=>true,'data'=>$m->find($id)]); }
    public function update(int $id){ $tenant=$this->tenantId(); if(!$tenant)return $this->fail('Unauthenticated.',401); $m=new MessageTemplateModel(); $row=$m->where('tenant_id',$tenant)->find($id); if(!$row)return $this->fail('Template tidak ditemukan.',404); $d=$this->request->getJSON(true)??[]; unset($d['tenant_id']); if(isset($d['body']))$d['variables_json']=json_encode((new TemplateRendererService())->variables((string)$d['body'])); $m->update($id,$d); return $this->respond(['success'=>true,'data'=>$m->find($id)]); }
    public function delete(int $id){ $tenant=$this->tenantId(); if(!$tenant)return $this->fail('Unauthenticated.',401); $m=new MessageTemplateModel(); if(!$m->where('tenant_id',$tenant)->find($id))return $this->fail('Template tidak ditemukan.',404); $m->delete($id); return $this->respondDeleted(['success'=>true]); }
}
