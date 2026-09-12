<?php
namespace App\Controllers;

use CodeIgniter\Controller;

class InboxView extends Controller
{
    public function index()
    {
        return view('inbox/index');
    }
}
