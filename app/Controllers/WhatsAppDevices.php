<?php

namespace App\Controllers;

use App\Models\WhatsAppDeviceModel;

class WhatsAppDevices extends BaseApiController
{
    public function index()
    {
        $tenantId = $this->tenantId();
        if (!$tenantId) return $this->fail('Unauthenticated.', 401);
        $rows = (new WhatsAppDeviceModel())->where('tenant_id', $tenantId)->orderBy('id', 'DESC')->findAll();
        return $this->respond(['success' => true, 'data' => $rows]);
    }

    public function create()
    {
        $tenantId = $this->tenantId();
        if (!$tenantId) return $this->fail('Unauthenticated.', 401);
        $data = $this->request->getJSON(true) ?? [];
        $name = trim((string)($data['name'] ?? ''));
        if ($name === '') return $this->fail('Nama device wajib diisi.');
        $model = new WhatsAppDeviceModel();
        $id = $model->insert([
            'tenant_id' => $tenantId,
            'name' => $name,
            'phone_number' => $data['phone_number'] ?? null,
            'session_id' => 'tenant-'.$tenantId.'-'.bin2hex(random_bytes(8)),
            'status' => 'DISCONNECTED',
        ], true);
        $device = $model->find($id);
        return $this->respondCreated(['success' => true, 'data' => $device]);
    }
}
