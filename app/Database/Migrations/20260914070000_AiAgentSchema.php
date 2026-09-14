<?php
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AiAgentSchema extends Migration
{
    public function up()
    {
        $this->db->query("CREATE TABLE ai_agent_settings (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL, enabled TINYINT(1) NOT NULL DEFAULT 0, auto_reply TINYINT(1) NOT NULL DEFAULT 0, confidence_threshold DECIMAL(5,2) NOT NULL DEFAULT 75, style VARCHAR(40) NOT NULL DEFAULT 'friendly', max_reply_chars INT UNSIGNED NOT NULL DEFAULT 1500, created_at DATETIME NULL, updated_at DATETIME NULL, UNIQUE KEY uq_ai_agent_tenant(tenant_id), CONSTRAINT fk_ai_agent_tenant FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function down()
    {
        $this->db->query('DROP TABLE IF EXISTS ai_agent_settings');
    }
}
