<?php

namespace App\Http\Controllers\Counselor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CounselorDashboardController extends Controller
{
    public function index(Request $request)
    {
        // Later we’ll pass: pending high-risk items count, today’s appointments, quick availability summary.
        return view('Counselor_Interface.dashboard');
    }
}
