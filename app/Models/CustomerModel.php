<?php
namespace App\Models;

use CodeIgniter\Model;

class CustomerModel extends Model
{
    protected $table = 'customers';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = ['tenant_id','phone','name','email','company','address','source','status','owner_id','notes'];
    protected $useTimestamps = true;
}
