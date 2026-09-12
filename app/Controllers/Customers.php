<?php
namespace App\Controllers;

use App\Models\CustomerModel;

class Customers extends BaseApiController
{
    public function index()
    {
        $tenantId = $this->tenantId();
        if (!$tenantId) return $this->fail('Unauthenticated.', 401);
        $model = new CustomerModel();
        $query = $model->where('tenant_id', $tenantId);
        $search = trim((string)$this->request->getGet('search'));
        if ($search !== '') $query->groupStart()->like('name', $search)->orLike('phone', $search)->orLike('email', $search)->orLike('company', $search)->groupEnd();
        return $this->respond(['success' => true, 'data' => $query->orderBy('id', 'DESC')->paginate(25), 'pager' => $model->pager]);
    }

    public function show(int $id)
    {
        $tenantId = $this->tenantId();
        if (!$tenantId) return $this->fail('Unauthenticated.', 401);
        $row = (new CustomerModel())->where('tenant_id', $tenantId)->find($id);
        if (!$row) return $this->fail('Customer tidak ditemukan.', 404);
        return $this->respond(['success' => true, 'data' => $row]);
    }

    public function create()
    {
        $tenantId = $this->tenantId();
        if (!$tenantId) return $this->fail('Unauthenticated.', 401);
        $data = $this->request->getJSON(true) ?? [];
        $phone = preg_replace('/\D+/', '', (string)($data['phone'] ?? ''));
        if (str_starts_with($phone, '0')) $phone = '62' . substr($phone, 1);
        if (str_starts_with($phone, '62') && strlen($phone) < 10) return $this->fail('Nomor WhatsApp tidak valid.');
        if ($phone === '') return $this->fail('Nomor telepon wajib diisi.');
        $model = new CustomerModel();
        $existing = $model->where('tenant_id', $tenantId)->where('phone', $phone)->first();
        if ($existing) return $this->fail('Nomor customer sudah terdaftar.', 409);
        $id = $model->insert([
            'tenant_id' => $tenantId,
            'phone' => $phone,
            'name' => trim((string)($data['name'] ?? '')) ?: null,
            'email' => $data['email'] ?? null,
            'company' => $data['company'] ?? null,
            'address' => $data['address'] ?? null,
            'source' => $data['source'] ?? 'MANUAL',
            'status' => $data['status'] ?? 'ACTIVE',
            'owner_id' => $data['owner_id'] ?? null,
            'notes' => $data['notes'] ?? null,
        ], true);
        return $this->respondCreated(['success' => true, 'data' => $model->find($id)]);
    }

    public function update(int $id)
    {
        $tenantId = $this->tenantId();
        if (!$tenantId) return $this->fail('Unauthenticated.', 401);
        $model = new CustomerModel();
        $row = $model->where('tenant_id', $tenantId)->find($id);
        if (!$row) return $this->fail('Customer tidak ditemukan.', 404);
        $data = $this->request->getJSON(true) ?? [];
        unset($data['tenant_id']);
        if (isset($data['phone'])) {
            $phone = preg_replace('/\D+/', '', (string)$data['phone']);
            if (str_starts_with($phone, '0')) $phone = '62' . substr($phone, 1);
            $data['phone'] = $phone;
        }
        $model->update($id, $data);
        return $this->respond(['success' => true, 'data' => $model->find($id)]);
    }
}
