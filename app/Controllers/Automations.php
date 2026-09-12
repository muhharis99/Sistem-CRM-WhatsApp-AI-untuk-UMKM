<?php
namespace App\Controllers;
use App\Models\AutomationRuleModel;

class Automations extends BaseApiController
{
    private array $triggers=['incoming_message','new_customer','tag_added','deal_stage_changed','no_reply_for_x_hours','scheduled_time'];
    public function index(){ $t=$this->tenantId(); if(!$t)return $this->fail('Unauthenticated.',401); return $this->respond(['success'=>true,'data'=>(new AutomationRuleModel())->where('tenant_id',$t)->orderBy('id','DESC')->findAll()]); }
    public function create(){ $t=$this->tenantId(); if(!$t)return $this->fail('Unauthenticated.',401); $d=$this->request->getJSON(true)??[]; $trigger=(string)($d['trigger_event']??''); if(!in_array($trigger,$this->triggers,true))return $this->fail('Trigger tidak valid.'); if(empty($d['actions'])||!is_array($d['actions']))return $this->fail('Action wajib diisi.'); $m=new AutomationRuleModel(); $id=$m->insert(['tenant_id'=>$t,'name'=>trim((string)($d['name']??'Automation')),'trigger_event'=>$trigger,'conditions_json'=>json_encode($d['conditions']??[]),'actions_json'=>json_encode($d['actions']),'enabled'=>0],true); return $this->respondCreated(['success'=>true,'data'=>$m->find($id)]); }
    public function toggle(int $id){ $t=$this->tenantId(); if(!$t)return $this->fail('Unauthenticated.',401); $m=new AutomationRuleModel(); $r=$m->where('tenant_id',$t)->find($id); if(!$r)return $this->fail('Automation tidak ditemukan.',404); $enabled=(int)($this->request->getJSON(true)['enabled']??0); $m->update($id,['enabled'=>$enabled?1:0]); return $this->respond(['success'=>true,'data'=>$m->find($id)]); }
}
