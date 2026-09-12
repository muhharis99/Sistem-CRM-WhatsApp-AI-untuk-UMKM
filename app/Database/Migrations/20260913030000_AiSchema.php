<?php
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AiSchema extends Migration
{
    public function up()
    {
        $sql = [
            "CREATE TABLE knowledge_bases (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL, name VARCHAR(150) NOT NULL, description TEXT NULL, active TINYINT(1) NOT NULL DEFAULT 1, created_at DATETIME NULL, updated_at DATETIME NULL, UNIQUE KEY uq_kb_tenant_name(tenant_id,name), KEY idx_kb_tenant(tenant_id), CONSTRAINT fk_kb_tenant FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE knowledge_documents (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL, knowledge_base_id BIGINT UNSIGNED NOT NULL, title VARCHAR(200) NOT NULL, content LONGTEXT NOT NULL, source VARCHAR(120) NULL, active TINYINT(1) NOT NULL DEFAULT 1, created_at DATETIME NULL, updated_at DATETIME NULL, KEY idx_kd_tenant(tenant_id), KEY idx_kd_kb(knowledge_base_id), CONSTRAINT fk_kd_tenant FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE, CONSTRAINT fk_kd_kb FOREIGN KEY(knowledge_base_id) REFERENCES knowledge_bases(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE knowledge_chunks (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL, document_id BIGINT UNSIGNED NOT NULL, chunk_index INT UNSIGNED NOT NULL, content TEXT NOT NULL, created_at DATETIME NULL, KEY idx_kc_tenant(tenant_id), KEY idx_kc_document(document_id), UNIQUE KEY uq_kc_document_index(document_id,chunk_index), CONSTRAINT fk_kc_tenant FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE, CONSTRAINT fk_kc_document FOREIGN KEY(document_id) REFERENCES knowledge_documents(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE ai_conversations (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL, conversation_id BIGINT UNSIGNED NOT NULL, summary TEXT NULL, intent VARCHAR(100) NULL, sentiment VARCHAR(50) NULL, lead_score DECIMAL(5,2) NULL, next_action VARCHAR(255) NULL, human_handoff TINYINT(1) NOT NULL DEFAULT 0, updated_at DATETIME NULL, created_at DATETIME NULL, UNIQUE KEY uq_ai_conv_tenant(conversation_id,tenant_id), CONSTRAINT fk_aic_tenant FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE, CONSTRAINT fk_aic_conversation FOREIGN KEY(conversation_id) REFERENCES conversations(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE ai_messages (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL, ai_conversation_id BIGINT UNSIGNED NULL, conversation_id BIGINT UNSIGNED NOT NULL, type VARCHAR(50) NOT NULL, prompt LONGTEXT NULL, response LONGTEXT NULL, confidence DECIMAL(5,2) NULL, provider VARCHAR(50) NULL, model VARCHAR(120) NULL, usage_json JSON NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, KEY idx_aim_tenant(tenant_id), KEY idx_aim_conversation(conversation_id), CONSTRAINT fk_aim_tenant FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE, CONSTRAINT fk_aim_ai_conv FOREIGN KEY(ai_conversation_id) REFERENCES ai_conversations(id) ON DELETE SET NULL, CONSTRAINT fk_aim_conversation FOREIGN KEY(conversation_id) REFERENCES conversations(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE ai_usage_logs (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL, provider VARCHAR(50) NOT NULL, model VARCHAR(120) NULL, operation VARCHAR(80) NOT NULL, input_tokens INT UNSIGNED NULL, output_tokens INT UNSIGNED NULL, total_tokens INT UNSIGNED NULL, latency_ms INT UNSIGNED NULL, estimated_cost DECIMAL(18,8) NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, KEY idx_aiu_tenant(tenant_id), KEY idx_aiu_created(created_at), CONSTRAINT fk_aiu_tenant FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        ];
        foreach ($sql as $query) $this->db->query($query);
    }

    public function down()
    {
        foreach (['ai_usage_logs','ai_messages','ai_conversations','knowledge_chunks','knowledge_documents','knowledge_bases'] as $table) {
            $this->db->query('DROP TABLE IF EXISTS ' . $this->db->protectIdentifiers($table));
        }
    }
}
