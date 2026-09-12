<?php

namespace App\Models;

use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table = 'users';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = ['tenant_id','role_id','name','email','password_hash','status','last_login_at'];
    protected $useTimestamps = true;
}
