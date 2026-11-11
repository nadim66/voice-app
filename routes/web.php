<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');

Route::get('/health', function () {
    DB::select('SELECT 1');

    return response()->json([
        'status' => 'ok',
        'revision' => is_file(base_path('REVISION')) ? trim(file_get_contents(base_path('REVISION'))) : 'local',
    ])->header('Cache-Control', 'no-store');
});
