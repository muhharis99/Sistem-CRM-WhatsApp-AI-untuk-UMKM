<?php
namespace App\Controllers;

class Analytics extends BaseApiController
{
    private function range(): array
    {
        $to = trim((string) $this->request->getGet('to')) ?: date('Y-m-d');
        $from = trim((string) $this->request->getGet('from')) ?: date('Y-m-d', strtotime('-29 days'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = date('Y-m-d', strtotime('-29 days'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) $to = date('Y-m-d');
        if ($from > $to) [$from, $to] = [$to, $from];
        return [$from . ' 00:00:00', $to . ' 23:59:59', $from, $to];
    }

    public function dashboard()
    {
        $tenantId = $this->tenantId();
        if (!$tenantId) return $this->fail('Unauthenticated.', 401);
        [$from, $to, $fromDate, $toDate] = $this->range();
        $db = db_connect();

        $count = static fn(string $table, string $where = '') => (int) $db->query("SELECT COUNT(*) n FROM {$table} WHERE tenant_id = ? {$where}", [$tenantId])->getRow()->n;

        $customers = (int) $db->table('customers')->where('tenant_id', $tenantId)->countAllResults();
        $newCustomers = $count('customers', 'AND created_at BETWEEN ? AND ?');
        $conversations = $count('conversations', 'AND created_at BETWEEN ? AND ?');
        $openConversations = (int) $db->table('conversations')->where('tenant_id', $tenantId)->whereIn('status', ['OPEN','PENDING'])->countAllResults();
        $messages = $count('messages', 'AND timestamp BETWEEN ? AND ?');
        $incoming = (int) $db->query("SELECT COUNT(*) n FROM messages WHERE tenant_id=? AND direction='INCOMING' AND timestamp BETWEEN ? AND ?", [$tenantId,$from,$to])->getRow()->n;
        $outgoing = (int) $db->query("SELECT COUNT(*) n FROM messages WHERE tenant_id=? AND direction='OUTGOING' AND timestamp BETWEEN ? AND ?", [$tenantId,$from,$to])->getRow()->n;
        $dealsOpen = (int) $db->table('deals')->where('tenant_id',$tenantId)->where('status','OPEN')->countAllResults();
        $pipelineValue = (float) ($db->query("SELECT COALESCE(SUM(value),0) v FROM deals WHERE tenant_id=? AND status='OPEN'",[$tenantId])->getRow()->v ?? 0);
        $wonValue = (float) ($db->query("SELECT COALESCE(SUM(value),0) v FROM deals WHERE tenant_id=? AND status='WON' AND updated_at BETWEEN ? AND ?",[$tenantId,$from,$to])->getRow()->v ?? 0);
        $wonDeals = (int) $db->query("SELECT COUNT(*) n FROM deals WHERE tenant_id=? AND status='WON' AND updated_at BETWEEN ? AND ?",[$tenantId,$from,$to])->getRow()->n;
        $closedDeals = $wonDeals + (int) $db->query("SELECT COUNT(*) n FROM deals WHERE tenant_id=? AND status='LOST' AND updated_at BETWEEN ? AND ?",[$tenantId,$from,$to])->getRow()->n;
        $conversionRate = $closedDeals ? round(($wonDeals / $closedDeals) * 100, 2) : 0;

        $campaign = $db->query("SELECT COUNT(*) campaigns, COALESCE(SUM(total_recipients),0) recipients, COALESCE(SUM(sent_count),0) sent, COALESCE(SUM(delivered_count),0) delivered, COALESCE(SUM(read_count),0) read_count, COALESCE(SUM(failed_count),0) failed FROM campaigns WHERE tenant_id=? AND created_at BETWEEN ? AND ?",[$tenantId,$from,$to])->getRowArray();
        $sentBase = max(1,(int)$campaign['sent']);
        $campaign['delivery_rate'] = round(((int)$campaign['delivered'] / $sentBase) * 100,2);
        $campaign['read_rate'] = round(((int)$campaign['read_count'] / $sentBase) * 100,2);

        $ai = $db->query("SELECT COUNT(*) total, ROUND(AVG(confidence),2) avg_confidence, SUM(CASE WHEN human_handoff=1 THEN 1 ELSE 0 END) handoffs FROM ai_messages am LEFT JOIN ai_conversations ac ON ac.conversation_id=am.conversation_id AND ac.tenant_id=am.tenant_id WHERE am.tenant_id=? AND am.created_at BETWEEN ? AND ?",[$tenantId,$from,$to])->getRowArray();
        $ai['avg_confidence'] = (float)($ai['avg_confidence'] ?? 0);
        $ai['handoffs'] = (int)($ai['handoffs'] ?? 0);
        $ai['human_handoff_rate'] = (int)$ai['total'] ? round(($ai['handoffs'] / $ai['total']) * 100,2) : 0;

        $daily = $db->query("SELECT DATE(timestamp) day, SUM(direction='INCOMING') incoming, SUM(direction='OUTGOING') outgoing FROM messages WHERE tenant_id=? AND timestamp BETWEEN ? AND ? GROUP BY DATE(timestamp) ORDER BY day ASC",[$tenantId,$from,$to])->getResultArray();
        $stages = $db->query("SELECT ps.name stage, COUNT(d.id) deals, COALESCE(SUM(d.value),0) value FROM deals d INNER JOIN pipeline_stages ps ON ps.id=d.stage_id INNER JOIN pipelines p ON p.id=d.pipeline_id WHERE d.tenant_id=? AND d.status='OPEN' GROUP BY ps.id,ps.name ORDER BY ps.position ASC",[$tenantId])->getResultArray();
        $agents = $db->query("SELECT u.id, u.name, COUNT(DISTINCT c.id) conversations, SUM(CASE WHEN c.status IN ('OPEN','PENDING') THEN 1 ELSE 0 END) active_conversations, COUNT(m.id) messages FROM users u LEFT JOIN conversations c ON c.assigned_to=u.id AND c.tenant_id=? AND c.created_at BETWEEN ? AND ? LEFT JOIN messages m ON m.conversation_id=c.id AND m.tenant_id=? AND m.timestamp BETWEEN ? AND ? WHERE u.tenant_id=? GROUP BY u.id,u.name ORDER BY conversations DESC",[$tenantId,$from,$to,$tenantId,$from,$to,$tenantId])->getResultArray();

        return $this->respond(['success'=>true,'data'=>[
            'range'=>['from'=>$fromDate,'to'=>$toDate],
            'kpi'=>[
                'customers'=>$customers,'new_customers'=>$newCustomers,'conversations'=>$conversations,'open_conversations'=>$openConversations,
                'messages'=>$messages,'incoming_messages'=>$incoming,'outgoing_messages'=>$outgoing,
                'open_deals'=>$dealsOpen,'pipeline_value'=>$pipelineValue,'won_value'=>$wonValue,'won_deals'=>$wonDeals,'conversion_rate'=>$conversionRate,
            ],
            'campaign'=>$campaign,'ai'=>$ai,'daily_messages'=>$daily,'pipeline'=>$stages,'agents'=>$agents,
        ]]);
    }
}
