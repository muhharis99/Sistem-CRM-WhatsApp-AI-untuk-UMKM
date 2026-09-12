<?php
namespace App\Services\AI;

use App\Models\AiConversationModel;
use App\Models\ConversationModel;

class AIService
{
    public function __construct(private ?AIProviderInterface $provider = null)
    {
        $this->provider ??= $this->makeProvider();
    }

    private function makeProvider(): AIProviderInterface
    {
        return match (strtolower((string) env('AI_PROVIDER', 'openai'))) {
            'openai' => new OpenAIProvider(),
            default => throw new \RuntimeException('AI provider belum didukung: '.env('AI_PROVIDER')),
        };
    }

    public function suggestReply(int $tenantId, int $conversationId, string $style = 'friendly'): array
    {
        $context = $this->conversationContext($tenantId, $conversationId);
        $system = 'Anda adalah asisten CS untuk UMKM. Jawab hanya berdasarkan konteks bisnis yang diberikan. Jika informasi tidak tersedia, katakan bahwa informasi belum tersedia dan jangan mengarang harga, stok, kebijakan, atau janji. Gaya: '.$style.'. Berikan hanya draft balasan pelanggan.';
        $result = $this->provider->generate($system, $context);
        $this->logAi($tenantId, $conversationId, 'reply_suggestion', $context, $result);
        return $result;
    }

    public function analyzeConversation(int $tenantId, int $conversationId): array
    {
        $context = $this->conversationContext($tenantId, $conversationId);
        $system = 'Analisis percakapan customer service. Balas JSON valid tanpa markdown dengan field: summary, intent, sentiment, lead_score, next_action, human_handoff. lead_score 0-100. human_handoff true untuk komplain berat, refund, permintaan manusia, risiko tinggi, atau informasi yang tidak dapat diverifikasi.';
        $result = $this->provider->generate($system, $context);
        $data = json_decode($this->stripJsonFences($result['text']), true);
        if (!is_array($data)) throw new \RuntimeException('Output analisis AI bukan JSON valid.');
        $data['lead_score'] = min(100, max(0, (float)($data['lead_score'] ?? 0)));
        $data['human_handoff'] = (bool)($data['human_handoff'] ?? false);
        $aiModel = new AiConversationModel();
        $existing = $aiModel->where('tenant_id', $tenantId)->where('conversation_id', $conversationId)->first();
        $payload = ['tenant_id'=>$tenantId,'conversation_id'=>$conversationId,'summary'=>$data['summary'] ?? null,'intent'=>$data['intent'] ?? null,'sentiment'=>$data['sentiment'] ?? null,'lead_score'=>$data['lead_score'],'next_action'=>$data['next_action'] ?? null,'human_handoff'=>$data['human_handoff'] ? 1 : 0];
        $existing ? $aiModel->update($existing['id'], $payload) : $aiModel->insert($payload);
        if ($data['human_handoff']) (new ConversationModel())->where('tenant_id',$tenantId)->update($conversationId, ['ai_enabled'=>0,'priority'=>'HIGH']);
        $this->logAi($tenantId, $conversationId, 'conversation_analysis', $context, $result);
        return $data;
    }

    public function knowledge(int $tenantId, string $query, int $limit = 6): array
    {
        $builder = db_connect()->table('knowledge_documents d')->select('d.title,d.content')->where('d.tenant_id',$tenantId)->where('d.active',1)->groupStart()->like('d.title',$query)->orLike('d.content',$query)->groupEnd()->limit($limit);
        $rows = $builder->get()->getResultArray();
        return array_map(fn($r) => ['title'=>$r['title'],'content'=>mb_substr($r['content'],0,4000)], $rows);
    }

    private function conversationContext(int $tenantId, int $conversationId): string
    {
        $db = db_connect();
        $conversation = $db->table('conversations c')->select('c.*,cu.name,cu.phone,cu.company,cu.notes')->join('customers cu','cu.id=c.customer_id')->where('c.id',$conversationId)->where('c.tenant_id',$tenantId)->get()->getRowArray();
        if (!$conversation) throw new \RuntimeException('Conversation tidak ditemukan.');
        $messages = $db->table('messages')->select('sender_type,direction,body,timestamp')->where('tenant_id',$tenantId)->where('conversation_id',$conversationId)->orderBy('timestamp','DESC')->limit(20)->get()->getResultArray();
        $messages = array_reverse($messages);
        $recent = implode("\n", array_map(fn($m) => '['.$m['timestamp'].'] '.$m['sender_type'].': '.mb_substr((string)$m['body'],0,2000), $messages));
        $knowledge = $this->knowledge($tenantId, (string)($messages[array_key_last($messages)]['body'] ?? ''), 5);
        $kb = implode("\n\n", array_map(fn($x) => 'KNOWLEDGE: '.$x['title']."\n".$x['content'], $knowledge));
        return "CUSTOMER:\nName: {$conversation['name']}\nPhone: {$conversation['phone']}\nCompany: {$conversation['company']}\nNotes: {$conversation['notes']}\n\nRECENT MESSAGES:\n{$recent}\n\nBUSINESS KNOWLEDGE:\n{$kb}";
    }

    private function stripJsonFences(string $text): string
    {
        return preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($text));
    }

    private function logAi(int $tenantId, int $conversationId, string $operation, string $prompt, array $result): void
    {
        db_connect()->table('ai_messages')->insert(['tenant_id'=>$tenantId,'conversation_id'=>$conversationId,'type'=>$operation,'prompt'=>$prompt,'response'=>$result['text'] ?? null,'provider'=>$result['provider'] ?? null,'model'=>$result['model'] ?? null,'usage_json'=>json_encode($result['usage'] ?? [], JSON_UNESCAPED_UNICODE)]);
        $usage = $result['usage'] ?? [];
        db_connect()->table('ai_usage_logs')->insert(['tenant_id'=>$tenantId,'provider'=>$result['provider'] ?? 'unknown','model'=>$result['model'] ?? null,'operation'=>$operation,'input_tokens'=>(int)($usage['input_tokens'] ?? 0),'output_tokens'=>(int)($usage['output_tokens'] ?? 0),'total_tokens'=>(int)($usage['total_tokens'] ?? 0),'latency_ms'=>(int)($result['latency_ms'] ?? 0)]);
    }
}
