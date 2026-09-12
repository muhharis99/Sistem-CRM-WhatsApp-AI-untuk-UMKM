<?php
namespace App\Services;

class WhatsAppGatewayService
{
    public function sendText(int $tenantId, int $deviceId, string $remoteJid, string $text): array
    {
        $base = rtrim((string) env('WHATSAPP_SERVICE_URL', 'http://127.0.0.1:3000'), '/');
        $secret = (string) env('WHATSAPP_GATEWAY_SECRET', '');
        $payload = json_encode(['tenant_id' => $tenantId, 'device_id' => $deviceId, 'remote_jid' => $remoteJid, 'text' => $text], JSON_UNESCAPED_UNICODE);
        $headers = ['Content-Type: application/json', 'X-Gateway-Secret: ' . $secret];
        $context = stream_context_create(['http' => ['method' => 'POST', 'header' => implode("\r\n", $headers), 'content' => $payload, 'timeout' => 20, 'ignore_errors' => true]]);
        $body = @file_get_contents($base . '/api/whatsapp/messages/send', false, $context);
        if ($body === false) throw new \RuntimeException('WhatsApp gateway tidak dapat dihubungi.');
        $decoded = json_decode($body, true);
        if (!is_array($decoded) || empty($decoded['success'])) throw new \RuntimeException((string)($decoded['message'] ?? 'Gateway menolak pengiriman pesan.'));
        return $decoded;
    }
}
