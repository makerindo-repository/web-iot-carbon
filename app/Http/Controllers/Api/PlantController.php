<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Plant;

class PlantController extends Controller
{
    public function index()
    {
        return response()->json(Plant::all());
    }
}
