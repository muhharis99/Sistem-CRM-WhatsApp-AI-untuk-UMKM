<?php
namespace App\Models;
use CodeIgniter\Model;
class CampaignModel extends Model
{
    protected $table='campaigns'; protected $primaryKey='id'; protected $returnType='array';
    protected $allowedFields=['tenant_id','device_id','name','template_id','message_body','segment_json','scheduled_at','timezone','status','rate_limit_per_minute','total_recipients','sent_count','delivered_count','read_count','failed_count','created_by']; protected $useTimestamps=true;
}
