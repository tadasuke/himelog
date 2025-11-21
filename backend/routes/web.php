<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;

Route::get('/', function () {
    return response()->json(['message' => 'Himelog API', 'status' => 'ok']);
});

