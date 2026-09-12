<?php
namespace App\Models;

use CodeIgniter\Model;

class MessageModel extends Model
{
    protected $table = 'messages';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = ['tenant_id','conversation_id','device_id','message_id','remote_jid','sender_type','sender_phone','message_type','body','media_url','media_mime','quoted_message_id','status','direction','timestamp'];
    protected $useTimestamps = true;
}
