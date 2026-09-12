<?php

namespace App\Controllers;

class Home extends BaseController
{
    public function index()
    {
        return $this->response->setJSON([
            'name' => 'CRM WhatsApp + AI untuk UMKM',
            'status' => 'running',
            'service' => 'codeigniter-crm',
        ]);
    }
}
