<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\TelegramBotController;

Route::get('/telegram/updates', [TelegramBotController::class, 'getUpdates']);
Route::get('/telegram/send-message', [TelegramBotController::class, 'sendMessage']);

//Route::post('/telegram/webhook', [TelegramBotController::class, 'webhook']); // Webhook
//Route::get('/telegram/send-message', [TelegramBotController::class, 'sendMessage']); // Xabar yuborish
//Route::get('/telegram/updates', [TelegramBotController::class, 'getUpdates']); // Localhost uchun

