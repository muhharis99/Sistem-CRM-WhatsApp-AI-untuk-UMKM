<?php
namespace App\Services;

use App\Models\AutomationRuleModel;
use CodeIgniter\Database\BaseConnection;

class AutomationEngineService
{
    public function __construct(private ?BaseConnection $db = null)
    {
        $this->db ??= db_connect();
    }

    public function dispatch(int $tenantId, string $event, array $payload): array
    {
        if ($tenantId <= 0 || $event === '') return ['matched'=>0,'executed'=>0];
        $rules = (new AutomationRuleModel())
            ->where('tenant_id', $tenantId)
            ->where('trigger_event', $event)
            ->where('enabled', 1)
            ->orderBy('id', 'ASC')
            ->findAll();

        $matched = 0; $executed = 0;
        foreach ($rules as $rule) {
            if (!$this->matches($rule, $payload)) continue;
            $matched++;
            $eventId = (string)($payload['event_id'] ?? ($payload['message_id'] ?? sha1($event.'|'.json_encode($payload))));
            if (!$this->claimLog($tenantId, (int)$rule['id'], $eventId)) continue;
            try {
                $result = $this->executeActions($tenantId, $rule, $payload);
                $this->finishLog($tenantId, (int)$rule['id'], $eventId, 'SUCCESS', $payload, $result, null);
                $executed++;
            } catch (\Throwable $e) {
                $this->finishLog($tenantId, (int)$rule['id'], $eventId, 'FAILED', $payload, null, $e->getMessage());
            }
        }
        return ['matched'=>$matched,'executed'=>$executed];
    }

    private function matches(array $rule, array $payload): bool
    {
        $conditions = json_decode((string)($rule['conditions_json'] ?? '{}'), true) ?: [];
        foreach ($conditions as $path => $expected) {
            $actual = $this->value($payload, $path);
            if (is_array($expected) && isset($expected['operator'])) {
                $op = strtoupper((string)$expected['operator']); $value = $expected['value'] ?? null;
                $ok = match ($op) {
                    'EQUALS' => (string)$actual === (string)$value,
                    'NOT_EQUALS' => (string)$actual !== (string)$value,
                    'CONTAINS' => stripos((string)$actual, (string)$value) !== false,
                    'STARTS_WITH' => str_starts_with(strtolower((string)$actual), strtolower((string)$value)),
                    'IN' => in_array($actual, (array)$value, true),
                    'NOT_EMPTY' => $actual !== null && $actual !== '',
                    default => false,
                };
                if (!$ok) return false;
                continue;
            }
            if (is_array($expected) && !array_is_list($expected)) {
                if (!is_array($actual)) return false;
                if (!$this->matchesNested($actual, $expected)) return false;
                continue;
            }
            if (is_array($expected)) {
                if (!in_array($actual, $expected, true)) return false;
                continue;
            }
            if ((string)$actual !== (string)$expected) return false;
        }
        return true;
    }

    private function matchesNested(array $actual, array $expected): bool
    {
        foreach ($expected as $key => $value) {
            if (!array_key_exists($key, $actual)) return false;
            if (is_array($value)) {
                if (!$this->matchesNested((array)$actual[$key], $value)) return false;
            } elseif ((string)$actual[$key] !== (string)$value) return false;
        }
        return true;
    }

    private function executeActions(int $tenantId, array $rule, array $payload): array
    {
        $actions = json_decode((string)$rule['actions_json'], true) ?: [];
        $results = [];
        foreach ($actions as $action) {
            $type = strtoupper((string)($action['type'] ?? ''));
            $results[] = match ($type) {
                'TAG_CUSTOMER' => $this->tagCustomer($tenantId, $payload, (int)($action['tag_id'] ?? 0)),
                'CREATE_TASK' => $this->createTask($tenantId, $payload, $action),
                'CREATE_DEAL' => $this->createDeal($tenantId, $payload, $action),
                'ASSIGN_OWNER' => $this->assignOwner($tenantId, $payload, (int)($action['user_id'] ?? 0)),
                'SCHEDULE_MESSAGE' => $this->scheduleMessage($tenantId, $payload, $action),
                'ADD_NOTE' => $this->addNote($tenantId, $payload, (string)($action['body'] ?? '')),
                default => ['success'=>false,'skipped'=>'UNKNOWN_ACTION','type'=>$type],
            };
        }
        return $results;
    }

    private function tagCustomer(int $tenantId, array $payload, int $tagId): array
    {
        $customerId=(int)($payload['customer_id']??0); if(!$customerId||!$tagId) throw new \RuntimeException('TAG_CUSTOMER membutuhkan customer_id dan tag_id.');
        $valid=$this->db->table('tags')->where(['id'=>$tagId,'tenant_id'=>$tenantId])->countAllResults(); if(!$valid) throw new \RuntimeException('Tag tidak ditemukan.');
        $this->db->table('customer_tags')->ignore(true)->insert(['customer_id'=>$customerId,'tag_id'=>$tagId,'created_at'=>date('Y-m-d H:i:s')]);
        return ['success'=>true,'action'=>'TAG_CUSTOMER'];
    }

    private function createTask(int $tenantId, array $payload, array $action): array
    {
        $customerId=(int)($payload['customer_id']??0); if(!$customerId) throw new \RuntimeException('CREATE_TASK membutuhkan customer_id.');
        $this->db->table('tasks')->insert(['tenant_id'=>$tenantId,'customer_id'=>$customerId,'deal_id'=>(int)($payload['deal_id']??0)?:null,'assigned_to'=>(int)($action['assigned_to']??0)?:null,'title'=>(string)($action['title']??'Tugas otomatis'),'description'=>$action['description']??null,'due_at'=>$action['due_at']??null,'status'=>'OPEN','priority'=>strtoupper((string)($action['priority']??'NORMAL')),'created_at'=>date('Y-m-d H:i:s'),'updated_at'=>date('Y-m-d H:i:s')]);
        return ['success'=>true,'action'=>'CREATE_TASK'];
    }

    private function createDeal(int $tenantId, array $payload, array $action): array
    {
        $customerId=(int)($payload['customer_id']??0); $pipelineId=(int)($action['pipeline_id']??0); $stageId=(int)($action['stage_id']??0); if(!$customerId||!$pipelineId||!$stageId) throw new \RuntimeException('CREATE_DEAL membutuhkan customer_id, pipeline_id dan stage_id.');
        $this->db->table('deals')->insert(['tenant_id'=>$tenantId,'pipeline_id'=>$pipelineId,'stage_id'=>$stageId,'customer_id'=>$customerId,'owner_id'=>(int)($action['owner_id']??0)?:null,'title'=>(string)($action['title']??'Deal otomatis'),'value'=>(float)($action['value']??0),'currency'=>$action['currency']??'IDR','status'=>'OPEN','expected_close_date'=>$action['expected_close_date']??null,'notes'=>$action['notes']??null,'created_at'=>date('Y-m-d H:i:s'),'updated_at'=>date('Y-m-d H:i:s')]);
        return ['success'=>true,'action'=>'CREATE_DEAL'];
    }

    private function assignOwner(int $tenantId, array $payload, int $userId): array
    {
        $customerId=(int)($payload['customer_id']??0); if(!$customerId||!$userId) throw new \RuntimeException('ASSIGN_OWNER membutuhkan customer_id dan user_id.');
        $valid=$this->db->table('users')->where(['id'=>$userId,'tenant_id'=>$tenantId])->countAllResults(); if(!$valid) throw new \RuntimeException('User owner tidak ditemukan.');
        $this->db->table('customers')->where(['id'=>$customerId,'tenant_id'=>$tenantId])->update(['owner_id'=>$userId,'updated_at'=>date('Y-m-d H:i:s')]);
        return ['success'=>true,'action'=>'ASSIGN_OWNER'];
    }

    private function scheduleMessage(int $tenantId, array $payload, array $action): array
    {
        $customerId=(int)($payload['customer_id']??0); $body=trim((string)($action['body']??'')); if(!$customerId||$body===''||empty($action['scheduled_at'])) throw new \RuntimeException('SCHEDULE_MESSAGE membutuhkan customer_id, body dan scheduled_at.');
        $this->db->table('scheduled_messages')->insert(['tenant_id'=>$tenantId,'customer_id'=>$customerId,'device_id'=>(int)($action['device_id']??0)?:null,'template_id'=>(int)($action['template_id']??0)?:null,'body'=>$body,'scheduled_at'=>$action['scheduled_at'],'timezone'=>$action['timezone']??'Asia/Jakarta','status'=>'PENDING','attempts'=>0,'max_attempts'=>min(10,max(1,(int)($action['max_attempts']??3))),'created_at'=>date('Y-m-d H:i:s'),'updated_at'=>date('Y-m-d H:i:s')]);
        return ['success'=>true,'action'=>'SCHEDULE_MESSAGE'];
    }

    private function addNote(int $tenantId, array $payload, string $body): array
    {
        $customerId=(int)($payload['customer_id']??0); if(!$customerId||$body==='') throw new \RuntimeException('ADD_NOTE membutuhkan customer_id dan body.');
        $this->db->table('customer_notes')->insert(['tenant_id'=>$tenantId,'customer_id'=>$customerId,'user_id'=>null,'body'=>$body,'created_at'=>date('Y-m-d H:i:s'),'updated_at'=>date('Y-m-d H:i:s')]);
        return ['success'=>true,'action'=>'ADD_NOTE'];
    }

    private function claimLog(int $tenantId,int $ruleId,string $eventId): bool
    {
        $existing=$this->db->table('automation_logs')->where(['tenant_id'=>$tenantId,'automation_rule_id'=>$ruleId,'event_id'=>$eventId])->countAllResults(); if($existing>0)return false;
        $this->db->table('automation_logs')->insert(['tenant_id'=>$tenantId,'automation_rule_id'=>$ruleId,'event_id'=>$eventId,'status'=>'RUNNING','started_at'=>date('Y-m-d H:i:s'),'created_at'=>date('Y-m-d H:i:s')]);
        return true;
    }

    private function finishLog(int $tenantId,int $ruleId,string $eventId,string $status,array $input,?array $output,?string $error): void
    {
        $this->db->table('automation_logs')->where(['tenant_id'=>$tenantId,'automation_rule_id'=>$ruleId,'event_id'=>$eventId])->update(['status'=>$status,'input_json'=>json_encode($input),'output_json'=>$output?json_encode($output):null,'error_message'=>$error,'finished_at'=>date('Y-m-d H:i:s')]);
    }

    private function value(array $payload,string $path): mixed
    {
        $current=$payload; foreach(explode('.',$path) as $segment){if(!is_array($current)||!array_key_exists($segment,$current))return null;$current=$current[$segment];} return $current;
    }
}
