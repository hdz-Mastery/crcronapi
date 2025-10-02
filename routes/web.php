<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'message' => 'CRCRON API v1.0',
        'status' => 'active',
        'documentation' => url('/api/docs'),
    ]);
});