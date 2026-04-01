<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

class ActivityLogController extends Controller
{
    public function index()
    {
        $logs = Activity::latest()->take(10)->get();

        return view('pages.activity-log.index', compact('logs'));
    }
}
