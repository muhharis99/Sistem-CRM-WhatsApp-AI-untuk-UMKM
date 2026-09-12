<?php
namespace App\Models;
use CodeIgniter\Model;
class MessageTemplateModel extends Model
{
    protected $table='message_templates'; protected $primaryKey='id'; protected $returnType='array';
    protected $allowedFields=['tenant_id','name','category','body','variables_json','active']; protected $useTimestamps=true;
}
