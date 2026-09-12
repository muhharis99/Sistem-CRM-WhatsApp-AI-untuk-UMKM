<?php
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class QueueSchema extends Migration
{
    public function up()
    {
        $this->db->query("ALTER TABLE campaigns ADD COLUMN device_id BIGINT UNSIGNED NULL AFTER tenant_id, ADD KEY idx_campaign_device(device_id), ADD CONSTRAINT fk_campaign_device FOREIGN KEY(device_id) REFERENCES whatsapp_devices(id) ON DELETE SET NULL");
        $this->db->query("ALTER TABLE campaign_recipients ADD COLUMN attempts TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER status, ADD COLUMN next_attempt_at DATETIME NULL AFTER error_message, ADD KEY idx_cr_queue(status,next_attempt_at)");
    }

    public function down()
    {
        $this->db->query("ALTER TABLE campaign_recipients DROP COLUMN next_attempt_at, DROP COLUMN attempts, DROP KEY idx_cr_queue(status,next_attempt_at)");
        $this->db->query("ALTER TABLE campaigns DROP FOREIGN KEY fk_campaign_device, DROP COLUMN device_id, DROP KEY idx_campaign_device");
    }
}
