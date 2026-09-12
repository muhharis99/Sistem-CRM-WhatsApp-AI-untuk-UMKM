<?php

namespace App\Controllers;

use App\Models\WhatsAppDeviceModel;
use App\Services\CrmMessageService;

class WhatsAppWebhook extends BaseApiController
{
    public function receive()
    {
        $secret = (string) env('WHATSAPP_WEBHOOK_SECRET', '');
        $provided = (string) $this->request->getHeaderLine('X-WhatsApp-Gateway-Secret');
        if ($secret === '' || !hash_equals($secret, $provided)) return $this->fail('Invalid webhook signature.', 401);

        $payload = $this->request->getJSON(true) ?? [];
        $event = (string)($payload['event'] ?? '');
        $deviceId = isset($payload['device_id']) ? (int)$payload['device_id'] : null;

        if ($deviceId && in_array($event, ['device.connected','device.disconnected','device.qr','device.logged_out'], true)) {
            $statuses = [
                'device.connected' => 'CONNECTED',
                'device.disconnected' => 'DISCONNECTED',
                'device.qr' => 'QR_REQUIRED',
                'device.logged_out' => 'LOGGED_OUT',
            ];
            $updates = ['status' => $statuses[$event]];
            if ($event === 'device.connected') $updates['last_connected_at'] = date('Y-m-d H:i:s');
            if ($event === 'device.disconnected' || $event === 'device.logged_out') $updates['last_disconnected_at'] = date('Y-m-d H:i:s');
            (new WhatsAppDeviceModel())->update($deviceId, $updates);
        }

        if ($event === 'message.received') {
            $message = (new CrmMessageService())->ingestIncoming($payload);
            return $this->respond(['success' => true, 'received' => $event, 'message' => $message]);
        }

        return $this->respond(['success' => true, 'received' => $event]);
    }
}
