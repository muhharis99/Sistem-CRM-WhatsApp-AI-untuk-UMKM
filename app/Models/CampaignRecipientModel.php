<?php
namespace App\Models;

use CodeIgniter\Model;

class CampaignRecipientModel extends Model
{
    protected $table = 'campaign_recipients';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = ['tenant_id','campaign_id','customer_id','phone','rendered_body','status','attempts','message_id','error_message','next_attempt_at','sent_at','delivered_at','read_at'];
    protected $useTimestamps = true;
}
