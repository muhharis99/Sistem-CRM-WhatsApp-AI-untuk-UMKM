<?php
namespace App\Controllers;

use CodeIgniter\Controller;

class AnalyticsView extends Controller
{
    public function index()
    {
        return view('analytics/index');
    }
}
