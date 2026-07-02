<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index()
    {
        return view('home');
    }

    
    public function loaisanpham()
    {
        return view('loaisanpham');
    }

    public function thongtintaikhoan()
    {
        return view('thongtintaikhoan');
    }
}
