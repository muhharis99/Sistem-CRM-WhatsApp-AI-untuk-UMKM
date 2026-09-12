<?php
namespace App\Services;

class WhatsAppGatewayService
{
    private function request(string $path, array $payload): array
    {
        $base = rtrim((string) env('WHATSAPP_SERVICE_URL', 'http://127.0.0.1:3000'), '/');
        $secret = (string) env('WHATSAPP_GATEWAY_SECRET', '');
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $headers = ['Content-Type: application/json', 'X-Gateway-Secret: ' . $secret];
        $context = stream_context_create(['http' => ['method' => 'POST', 'header' => implode("\r\n", $headers), 'content' => $body, 'timeout' => 20, 'ignore_errors' => true]]);
        $response = @file_get_contents($base . $path, false, $context);
        if ($response === false) throw new \RuntimeException('WhatsApp gateway tidak dapat dihubungi.');
        $decoded = json_decode($response, true);
        if (!is_array($decoded) || empty($decoded['success'])) throw new \RuntimeException((string)($decoded['message'] ?? 'Gateway menolak permintaan.'));
        return $decoded;
    }

    public function sendText(int $tenantId, int $deviceId, string $remoteJid, string $text): array
    {
        return $this->request('/api/whatsapp/messages/send', ['tenant_id'=>$tenantId,'device_id'=>$deviceId,'remote_jid'=>$remoteJid,'text'=>$text]);
    }

    public function enqueueCampaign(int $tenantId, int $campaignId, int $deviceId, array $recipientIds, int $rateLimitPerMinute): array
    {
        return $this->request('/api/queue/campaigns/enqueue', [
            'tenant_id'=>$tenantId,
            'campaign_id'=>$campaignId,
            'device_id'=>$deviceId,
            'recipient_ids'=>array_values(array_map('intval',$recipientIds)),
            'rate_limit_per_minute'=>max(1,$rateLimitPerMinute),
        ]);
    }
}
