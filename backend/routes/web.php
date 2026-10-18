<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LinkRedirectController;

Route::get('/r/{code}', [LinkRedirectController::class, 'redirect'])->where('code', '[a-zA-Z0-9_-]+');
Route::get('/l/{code}', [LinkRedirectController::class, 'redirect'])->where('code', '[a-zA-Z0-9_-]+');

Route::get('/', function () {
    return view('welcome');
});
