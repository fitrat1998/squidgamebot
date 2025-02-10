<?php

use App\Http\Controllers\ControlController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TelegramBotController;

Route::resource('controler', ControlController::class);

Route::post('/telegram/webhook', [TelegramBotController::class, 'handleWebhook']);

