<?php

namespace App\Models;

use CodeIgniter\Model;

class WhatsAppDeviceModel extends Model
{
    protected $table = 'whatsapp_devices';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = ['tenant_id','name','phone_number','session_id','status','connection_state','last_connected_at','last_disconnected_at'];
    protected $useTimestamps = true;
}
