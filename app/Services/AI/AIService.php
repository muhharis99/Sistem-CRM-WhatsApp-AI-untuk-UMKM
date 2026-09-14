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

    public function autoReply(int $tenantId, int $conversationId, string $style = 'friendly', float $threshold = 75, int $maxChars = 1500): array
    {
        $conversation = db_connect()->table('conversations')->where(['tenant_id'=>$tenantId,'id'=>$conversationId])->get()->getRowArray();
        if (!$conversation) throw new \RuntimeException('Conversation tidak ditemukan.');
        if (!(int)$conversation['ai_enabled']) return ['sent'=>false,'reason'=>'AI_DISABLED'];
        $existingHandoff = db_connect()->table('ai_conversations')->where(['tenant_id'=>$tenantId,'conversation_id'=>$conversationId])->get()->getRowArray();
        if ($existingHandoff && (int)$existingHandoff['human_handoff']) return ['sent'=>false,'reason'=>'HUMAN_HANDOFF'];

        $context = $this->conversationContext($tenantId, $conversationId);
        $system = 'Anda adalah AI customer service UMKM. Gunakan hanya BUSINESS KNOWLEDGE dan RECENT MESSAGES. Jangan mengarang harga, stok, promo, kebijakan, jadwal, atau janji. Jika pertanyaan membutuhkan manusia, refund/komplain berat, data sensitif, atau informasi tidak tersedia, set human_handoff=true. Balasan harus singkat, natural untuk WhatsApp, maksimal '.$maxChars.' karakter. Balas JSON valid tanpa markdown dengan field: reply, confidence, intent, human_handoff.';
        $result = $this->provider->generate($system, $context);
        $data = json_decode($this->stripJsonFences($result['text']), true);
        if (!is_array($data)) throw new \RuntimeException('Output AI Agent bukan JSON valid.');
        $reply = trim((string)($data['reply'] ?? ''));
        $confidence = min(100, max(0, (float)($data['confidence'] ?? 0)));
        $handoff = (bool)($data['human_handoff'] ?? false);
        $this->logAi($tenantId, $conversationId, 'auto_reply', $context, $result);

        if ($handoff || $confidence < $threshold || $reply === '') {
            if ($handoff || $confidence < $threshold) {
                (new ConversationModel())->where('tenant_id',$tenantId)->update($conversationId,['ai_enabled'=>0,'priority'=>'HIGH']);
                $this->upsertAiConversation($tenantId,$conversationId,$data,true);
            }
            return ['sent'=>false,'reason'=>$handoff?'HUMAN_HANDOFF':'LOW_CONFIDENCE','confidence'=>$confidence,'intent'=>$data['intent']??null];
        }

        $reply = mb_substr($reply, 0, $maxChars);
        $customer = db_connect()->table('conversations c')->select('c.customer_id,c.device_id,cu.phone')->join('customers cu','cu.id=c.customer_id')->where(['c.tenant_id'=>$tenantId,'c.id'=>$conversationId])->get()->getRowArray();
        if (!$customer) throw new \RuntimeException('Customer conversation tidak ditemukan.');
        $db = db_connect();
        $now = date('Y-m-d H:i:s');
        $db->table('scheduled_messages')->insert(['tenant_id'=>$tenantId,'customer_id'=>$customer['customer_id'],'device_id'=>$customer['device_id'],'body'=>$reply,'scheduled_at'=>date('Y-m-d H:i:s',time()+2),'timezone'=>'Asia/Jakarta','status'=>'PENDING','attempts'=>0,'max_attempts'=>3,'created_at'=>$now,'updated_at'=>$now]);
        $this->upsertAiConversation($tenantId,$conversationId,$data,false);
        return ['sent'=>true,'queued'=>true,'reply'=>$reply,'confidence'=>$confidence,'intent'=>$data['intent']??null];
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
        $this->upsertAiConversation($tenantId,$conversationId,$data,$data['human_handoff']);
        if ($data['human_handoff']) (new ConversationModel())->where('tenant_id', $tenantId)->update($conversationId, ['ai_enabled'=>0,'priority'=>'HIGH']);
        $this->logAi($tenantId, $conversationId, 'conversation_analysis', $context, $result);
        return $data;
    }

    public function knowledge(int $tenantId, string $query, int $limit = 6): array
    {
        $builder = db_connect()->table('knowledge_documents d')->select('d.title,d.content')->where('d.tenant_id',$tenantId)->where('d.active',1)->groupStart()->like('d.title',$query)->orLike('d.content',$query)->groupEnd()->limit($limit);
        $rows = $builder->get()->getResultArray();
        return array_map(fn($r) => ['title'=>$r['title'],'content'=>mb_substr($r['content'],0,4000)], $rows);
    }

    public function agentSettings(int $tenantId): array
    {
        $db=db_connect();$row=$db->table('ai_agent_settings')->where('tenant_id',$tenantId)->get()->getRowArray();
        if(!$row){$db->table('ai_agent_settings')->insert(['tenant_id'=>$tenantId,'enabled'=>0,'auto_reply'=>0,'confidence_threshold'=>75,'style'=>'friendly','max_reply_chars'=>1500,'created_at'=>date('Y-m-d H:i:s'),'updated_at'=>date('Y-m-d H:i:s')]);$row=$db->table('ai_agent_settings')->where('tenant_id',$tenantId)->get()->getRowArray();}
        return $row;
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

    private function upsertAiConversation(int $tenantId,int $conversationId,array $data,bool $handoff): void
    {
        $m=new AiConversationModel();$existing=$m->where(['tenant_id'=>$tenantId,'conversation_id'=>$conversationId])->first();$payload=['tenant_id'=>$tenantId,'conversation_id'=>$conversationId,'summary'=>$data['summary']??null,'intent'=>$data['intent']??null,'sentiment'=>$data['sentiment']??null,'lead_score'=>min(100,max(0,(float)($data['lead_score']??$data['confidence']??0))),'next_action'=>$data['next_action']??null,'human_handoff'=>$handoff?1:0];$existing?$m->update($existing['id'],$payload):$m->insert($payload);
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
