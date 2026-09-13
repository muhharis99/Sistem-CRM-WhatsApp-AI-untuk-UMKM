<?php
namespace App\Controllers;

use App\Models\WhatsAppDeviceModel;
use App\Services\AutomationEngineService;
use App\Services\CrmMessageService;

class WhatsAppWebhook extends BaseApiController
{
    public function receive()
    {
        $secret=(string)env('WHATSAPP_WEBHOOK_SECRET','');$provided=(string)$this->request->getHeaderLine('X-WhatsApp-Gateway-Secret');
        if($secret===''||!hash_equals($secret,$provided))return $this->fail('Invalid webhook signature.',401);
        $payload=$this->request->getJSON(true)??[];$event=(string)($payload['event']??'');$deviceId=isset($payload['device_id'])?(int)$payload['device_id']:null;$tenantId=(int)($payload['tenant_id']??0);
        if($deviceId&&in_array($event,['device.connected','device.disconnected','device.qr','device.logged_out'],true)){
            $statuses=['device.connected'=>'CONNECTED','device.disconnected'=>'DISCONNECTED','device.qr'=>'QR_REQUIRED','device.logged_out'=>'LOGGED_OUT'];$updates=['status'=>$statuses[$event]];
            if($event==='device.connected')$updates['last_connected_at']=date('Y-m-d H:i:s');if($event==='device.disconnected'||$event==='device.logged_out')$updates['last_disconnected_at']=date('Y-m-d H:i:s');
            (new WhatsAppDeviceModel())->where('id',$deviceId)->where('tenant_id',$tenantId)->set($updates)->update();
        }
        if($event==='message.received'){
            $message=(new CrmMessageService())->ingestIncoming($payload);
            $automationPayload=array_merge($payload,['message_id'=>$message['id']??($payload['message_id']??null),'customer_id'=>$message['customer_id']??($payload['customer_id']??null),'event_id'=>$payload['message_id']??($message['id']??null)]);
            $automation=(new AutomationEngineService())->dispatch($tenantId,'incoming_message',$automationPayload);
            return $this->respond(['success'=>true,'received'=>$event,'message'=>$message,'automation'=>$automation]);
        }
        return $this->respond(['success'=>true,'received'=>$event]);
    }
}
