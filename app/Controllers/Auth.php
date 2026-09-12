<?php

namespace App\Controllers;

use App\Models\UserModel;

class Auth extends BaseApiController
{
    public function login()
    {
        $data = $this->request->getJSON(true) ?? [];
        $email = strtolower(trim((string)($data['email'] ?? '')));
        $password = (string)($data['password'] ?? '');
        if ($email === '' || $password === '') return $this->fail('Email dan password wajib diisi.');

        $user = (new UserModel())->where('email', $email)->where('status', 'ACTIVE')->first();
        if (!$user || !password_verify($password, $user['password_hash'])) return $this->fail('Email atau password salah.', 401);

        session()->regenerate(true);
        session()->set(['user_id' => $user['id'], 'tenant_id' => $user['tenant_id'], 'role_id' => $user['role_id']]);
        return $this->respond(['success' => true, 'user' => ['id' => $user['id'], 'name' => $user['name'], 'email' => $user['email']], 'csrf' => csrf_hash()]);
    }

    public function logout()
    {
        session()->destroy();
        return $this->respond(['success' => true]);
    }

    public function me()
    {
        $id = session('user_id');
        if (!$id) return $this->fail('Unauthenticated.', 401);
        $user = (new UserModel())->find($id);
        return $this->respond(['success' => true, 'user' => $user ? ['id' => $user['id'], 'name' => $user['name'], 'email' => $user['email'], 'tenant_id' => $user['tenant_id'], 'role_id' => $user['role_id']] : null]);
    }
}
