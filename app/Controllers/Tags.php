<?php
namespace App\Controllers;

class Tags extends BaseApiController
{
    public function index()
    {
        $tenantId = $this->tenantId();
        if (!$tenantId) return $this->fail('Unauthenticated.', 401);
        return $this->respond(['success' => true, 'data' => db_connect()->table('tags')->where('tenant_id',$tenantId)->orderBy('name','ASC')->get()->getResultArray()]);
    }

    public function create()
    {
        $tenantId = $this->tenantId();
        if (!$tenantId) return $this->fail('Unauthenticated.', 401);
        $data = $this->request->getJSON(true) ?? [];
        $name = trim((string)($data['name'] ?? ''));
        if ($name === '') return $this->fail('Nama tag wajib diisi.');
        $db = db_connect();
        if ($db->table('tags')->where('tenant_id',$tenantId)->where('name',$name)->countAllResults()) return $this->fail('Tag sudah ada.',409);
        $db->table('tags')->insert(['tenant_id'=>$tenantId,'name'=>$name,'color'=>$data['color'] ?? null,'created_at'=>date('Y-m-d H:i:s'),'updated_at'=>date('Y-m-d H:i:s')]);
        return $this->respondCreated(['success'=>true,'data'=>$db->table('tags')->where('id',$db->insertID())->get()->getRowArray()]);
    }

    public function attach(int $customerId, int $tagId)
    {
        $tenantId = $this->tenantId();
        if (!$tenantId) return $this->fail('Unauthenticated.', 401);
        $db = db_connect();
        $customer = $db->table('customers')->where('tenant_id',$tenantId)->where('id',$customerId)->get()->getRowArray();
        $tag = $db->table('tags')->where('tenant_id',$tenantId)->where('id',$tagId)->get()->getRowArray();
        if (!$customer || !$tag) return $this->fail('Customer atau tag tidak ditemukan.',404);
        $db->query('INSERT IGNORE INTO customer_tags(customer_id,tag_id,created_at) VALUES (?,?,?)',[$customerId,$tagId,date('Y-m-d H:i:s')]);
        return $this->respond(['success'=>true]);
    }

    public function detach(int $customerId, int $tagId)
    {
        $tenantId = $this->tenantId();
        if (!$tenantId) return $this->fail('Unauthenticated.', 401);
        $db = db_connect();
        $db->query('DELETE ct FROM customer_tags ct INNER JOIN customers c ON c.id=ct.customer_id INNER JOIN tags t ON t.id=ct.tag_id WHERE c.tenant_id=? AND t.tenant_id=? AND ct.customer_id=? AND ct.tag_id=?',[$tenantId,$tenantId,$customerId,$tagId]);
        return $this->respond(['success'=>true]);
    }
}
