<?php
namespace App\Models;

use CodeIgniter\Model;

class AiConversationModel extends Model
{
    protected $table = 'ai_conversations';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = ['tenant_id','conversation_id','summary','intent','sentiment','lead_score','next_action','human_handoff'];
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
}
