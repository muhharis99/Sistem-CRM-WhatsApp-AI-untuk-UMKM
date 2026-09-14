<?php
namespace App\Controllers;

use CodeIgniter\Controller;

class CustomerWorkspaceView extends Controller
{
    public function index(int $id)
    {
        return view('customer/workspace', ['customerId' => $id]);
    }
}
