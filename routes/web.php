<?php

use App\Http\Controllers\AI\ChatController;
use App\Http\Controllers\AI\ConversationController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin');
});

Route::middleware(['web', 'auth'])->prefix('admin/ai')->group(function () {
    Route::post('/chat', [ChatController::class, 'stream'])->name('ai.chat');
    Route::get('/conversations', [ConversationController::class, 'index'])->name('ai.conversations.index');
    Route::get('/conversations/{id}/messages', [ConversationController::class, 'messages'])->name('ai.conversations.messages');
    Route::patch('/conversations/{id}', [ConversationController::class, 'update'])->name('ai.conversations.update');
    Route::delete('/conversations/{id}', [ConversationController::class, 'destroy'])->name('ai.conversations.destroy');
});
