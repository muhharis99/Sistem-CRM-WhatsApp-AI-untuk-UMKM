<?php
namespace App\Services;

use App\Models\CampaignModel;
use App\Models\CampaignRecipientModel;
use CodeIgniter\Database\BaseConnection;

class BroadcastQueueService
{
    public function __construct(private ?BaseConnection $db = null){$this->db ??= db_connect();}
    public function enqueueCampaign(int $tenantId,int $campaignId): array
    {
        $campaignModel=new CampaignModel();$campaign=$campaignModel->where('tenant_id',$tenantId)->find($campaignId);if(!$campaign)throw new \RuntimeException('Campaign tidak ditemukan.');if(!in_array($campaign['status'],['DRAFT','QUEUED','SCHEDULED'],true))throw new \RuntimeException('Campaign tidak dapat di-enqueue pada status saat ini.');
        $deviceId=(int)($campaign['device_id']??0);if(!$deviceId){$device=$this->db->table('whatsapp_devices')->where('tenant_id',$tenantId)->whereIn('status',['CONNECTED','READY'])->orderBy('id','ASC')->get(1)->getRowArray();if(!$device)throw new \RuntimeException('Tidak ada WhatsApp device aktif untuk tenant ini.');$deviceId=(int)$device['id'];$campaignModel->update($campaignId,['device_id'=>$deviceId]);}
        $segment=json_decode((string)($campaign['segment_json']??'{}'),true)?:[];$query=$this->db->table('customers c')->select('c.id,c.phone,c.name,c.email,c.company,c.address,c.source,c.status,c.owner_id')->where('c.tenant_id',$tenantId)->where('c.phone !=','')->whereNotIn('c.id',static function($q)use($tenantId){$q->select('customer_id')->from('opt_outs')->where('tenant_id',$tenantId)->where('channel','WHATSAPP');});$this->applySegment($query,$segment,$tenantId);$customers=$query->get()->getResultArray();
        $renderer=new TemplateRendererService();$recipientModel=new CampaignRecipientModel();$insertedIds=[];$now=date('Y-m-d H:i:s');
        foreach($customers as $customer){$variables=['name'=>$customer['name']?:'Pelanggan','phone'=>$customer['phone'],'email'=>$customer['email']??'','company'=>$customer['company']??''];$body=$renderer->render((string)$campaign['message_body'],$variables);$this->db->query('INSERT INTO campaign_recipients (tenant_id,campaign_id,customer_id,phone,rendered_body,status,attempts,created_at,updated_at) VALUES (?,?,?,?,?,\'PENDING\',0,?,?) ON DUPLICATE KEY UPDATE phone=VALUES(phone),rendered_body=VALUES(rendered_body),updated_at=VALUES(updated_at)',[$tenantId,$campaignId,(int)$customer['id'],$customer['phone'],$body,$now,$now]);$row=$recipientModel->where('tenant_id',$tenantId)->where('campaign_id',$campaignId)->where('customer_id',(int)$customer['id'])->first();if($row&&in_array($row['status'],['PENDING','PROCESSING'],true))$insertedIds[]=(int)$row['id'];}
        $campaignModel->update($campaignId,['status'=>$insertedIds?'QUEUED':'EMPTY','total_recipients'=>count($insertedIds)]);$response=(new WhatsAppGatewayService())->enqueueCampaign($tenantId,$campaignId,$deviceId,$insertedIds,(int)$campaign['rate_limit_per_minute']);return ['campaign'=>$campaignModel->find($campaignId),'queued'=>count($insertedIds),'gateway'=>$response];
    }
    public function claim(int $tenantId,int $recipientId): array
    {
        $this->db->transStart();$row=$this->db->query("SELECT cr.*,c.name,c.email,c.company,ca.device_id,ca.status AS campaign_status,ca.rate_limit_per_minute FROM campaign_recipients cr INNER JOIN customers c ON c.id=cr.customer_id AND c.tenant_id=cr.tenant_id INNER JOIN campaigns ca ON ca.id=cr.campaign_id AND ca.tenant_id=cr.tenant_id WHERE cr.id=? AND cr.tenant_id=? LIMIT 1 FOR UPDATE",[$recipientId,$tenantId])->getRowArray();if(!$row){$this->db->transComplete();return ['claimed'=>false,'reason'=>'NOT_FOUND'];}if($row['status']!=='PENDING'){$this->db->transComplete();return ['claimed'=>false,'reason'=>'ALREADY_PROCESSED','recipient'=>$row];}$optedOut=$this->db->table('opt_outs')->where(['tenant_id'=>$tenantId,'customer_id'=>(int)$row['customer_id'],'channel'=>'WHATSAPP'])->countAllResults()>0;if($optedOut){$this->db->table('campaign_recipients')->where('id',$recipientId)->where('tenant_id',$tenantId)->update(['status'=>'SKIPPED','error_message'=>'Customer opted out','updated_at'=>date('Y-m-d H:i:s')]);$this->db->transComplete();return ['claimed'=>false,'reason'=>'OPTED_OUT'];}$this->db->table('campaign_recipients')->where('id',$recipientId)->where('tenant_id',$tenantId)->where('status','PENDING')->update(['status'=>'PROCESSING','attempts'=>(int)$row['attempts']+1,'updated_at'=>date('Y-m-d H:i:s')]);$this->db->transComplete();$row['status']='PROCESSING';$row['attempts']=(int)$row['attempts']+1;return ['claimed'=>true,'recipient'=>$row];
    }
    public function complete(int $tenantId,int $recipientId,string $status,?string $messageId=null,?string $error=null,?int $attempts=null): array
    {
        $recipientModel=new CampaignRecipientModel();$row=$recipientModel->where(['tenant_id'=>$tenantId,'id'=>$recipientId])->first();if(!$row)throw new \RuntimeException('Recipient tidak ditemukan.');$updates=['status'=>$status,'error_message'=>$error,'updated_at'=>date('Y-m-d H:i:s')];if($messageId)$updates['message_id']=$messageId;if($status==='SENT'){$updates['sent_at']=date('Y-m-d H:i:s');$updates['next_attempt_at']=null;}if($status==='PENDING')$updates['next_attempt_at']=date('Y-m-d H:i:s',time()+max(5,(int)(pow(2,max(0,((int)($attempts??$row['attempts']))-1))*5)));$recipientModel->update($recipientId,$updates);
        $campaignModel=new CampaignModel();$counts=$recipientModel->select("SUM(status='SENT') sent_count,SUM(status='FAILED') failed_count,SUM(status IN ('PENDING','PROCESSING')) active_count")->where('campaign_id',(int)$row['campaign_id'])->first();$active=(int)($counts['active_count']??0);$campaignModel->update((int)$row['campaign_id'],['sent_count'=>(int)($counts['sent_count']??0),'failed_count'=>(int)($counts['failed_count']??0),'status'=>$active===0?'COMPLETED':'QUEUED']);return ['success'=>true,'status'=>$status];
    }
    private function applySegment($query,array $segment,int $tenantId): void
    {
        foreach(['status','owner_id','source'] as $field)if(array_key_exists($field,$segment)&&$segment[$field]!==''&&$segment[$field]!==null)$query->where('c.'.$field,$segment[$field]);if(!empty($segment['customer_ids'])&&is_array($segment['customer_ids']))$query->whereIn('c.id',array_map('intval',$segment['customer_ids']));if(!empty($segment['tag_id'])){$query->join('customer_tags ct','ct.customer_id=c.id','inner')->where('ct.tag_id',(int)$segment['tag_id']);$query->join('tags tg','tg.id=ct.tag_id','inner')->where('tg.tenant_id',$tenantId);}
    }
}
