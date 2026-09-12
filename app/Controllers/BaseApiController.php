<?php

namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;

abstract class BaseApiController extends ResourceController
{
    protected function tenantId(): ?int
    {
        $tenant = session('tenant_id');
        return $tenant ? (int) $tenant : null;
    }

    protected function fail(string $message, int $status = 422, array $errors = [])
    {
        return $this->respond(['success' => false, 'message' => $message, 'errors' => $errors], $status);
    }
}
