<?php
namespace App\Models;

use CodeIgniter\Model;

class ConversationModel extends Model
{
    protected $table = 'conversations';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = ['tenant_id','device_id','customer_id','status','assigned_to','last_message_at','unread_count','ai_enabled','priority'];
    protected $useTimestamps = true;
}
