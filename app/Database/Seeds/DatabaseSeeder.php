<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        $db = db_connect();
        $now = date('Y-m-d H:i:s');
        $db->table('roles')->insertBatch([
            ['name' => 'super_admin', 'description' => 'Platform administrator', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'owner', 'description' => 'Tenant owner', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'manager', 'description' => 'Tenant manager', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'sales', 'description' => 'Sales agent', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'cs', 'description' => 'Customer service agent', 'created_at' => $now, 'updated_at' => $now],
        ]);
        $permissions = [];
        foreach (['customer.view','customer.create','customer.edit','customer.delete','conversation.view','conversation.reply','conversation.assign','whatsapp.view','whatsapp.connect','whatsapp.disconnect','deal.view','deal.create','deal.edit','broadcast.view','broadcast.create','broadcast.send','ai.use','ai.configure','report.view','setting.manage'] as $name) $permissions[] = ['name' => $name, 'created_at' => $now, 'updated_at' => $now];
        $db->table('permissions')->insertBatch($permissions);
        $role = $db->table('roles')->where('name','owner')->get()->getFirstRow('array');
        $perms = $db->table('permissions')->get()->getResultArray();
        foreach ($perms as $permission) $db->table('role_permissions')->insert(['role_id' => $role['id'], 'permission_id' => $permission['id']]);
        $db->table('tenants')->insert(['name' => 'Demo UMKM', 'slug' => 'demo-umkm', 'status' => 'ACTIVE', 'timezone' => 'Asia/Jakarta', 'created_at' => $now, 'updated_at' => $now]);
        $tenantId = $db->insertID();
        $db->table('users')->insert(['tenant_id' => $tenantId, 'role_id' => $role['id'], 'name' => 'Owner Demo', 'email' => 'admin@example.test', 'password_hash' => password_hash('ChangeMe123!', PASSWORD_DEFAULT), 'status' => 'ACTIVE', 'created_at' => $now, 'updated_at' => $now]);
    }
}
