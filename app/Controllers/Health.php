<?php

namespace App\Controllers;

class Health extends BaseController
{
    public function index()
    {
        return $this->response->setJSON([
            'success' => true,
            'service' => 'crm-api',
            'status' => 'healthy',
            'timestamp' => date(DATE_ATOM),
        ]);
    }
}
