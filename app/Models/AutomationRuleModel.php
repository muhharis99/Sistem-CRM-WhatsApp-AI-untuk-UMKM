<?php
namespace App\Models;
use CodeIgniter\Model;
class AutomationRuleModel extends Model
{
    protected $table='automation_rules'; protected $primaryKey='id'; protected $returnType='array';
    protected $allowedFields=['tenant_id','name','trigger_event','conditions_json','actions_json','enabled']; protected $useTimestamps=true;
}
