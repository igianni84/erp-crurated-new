<?php

use App\Http\Controllers\AI\ChatController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/admin/ai/chat', [ChatController::class, 'stream'])
    ->middleware(['web', 'auth'])
    ->name('ai.chat');
