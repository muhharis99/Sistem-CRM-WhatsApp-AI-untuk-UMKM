<?php
namespace App\Models;
use CodeIgniter\Model;
class ScheduledMessageModel extends Model
{
    protected $table='scheduled_messages'; protected $primaryKey='id'; protected $returnType='array';
    protected $allowedFields=['tenant_id','customer_id','device_id','template_id','body','scheduled_at','timezone','status','attempts','max_attempts','last_error','message_id','sent_at','created_by']; protected $useTimestamps=true;
}
