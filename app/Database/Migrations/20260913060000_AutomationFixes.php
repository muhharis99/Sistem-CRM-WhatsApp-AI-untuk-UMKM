<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;
class AutomationFixes extends Migration
{
    public function up()
    {
        $this->db->query("ALTER TABLE automation_logs DROP INDEX idx_al_rule, ADD KEY idx_al_rule(automation_rule_id,event_id), ADD UNIQUE KEY uq_al_event(tenant_id,automation_rule_id,event_id)");
    }
    public function down()
    {
        $this->db->query("ALTER TABLE automation_logs DROP INDEX uq_al_event, DROP INDEX idx_al_rule, ADD KEY idx_al_rule(automation_rule_id)");
    }
}
