<?php


namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Telegram\Bot\Laravel\Facades\Telegram;

class TelegramBotController extends Controller
{
    // Botga kelgan so'rovlarni olish (Long Polling uchun)
    public function getUpdates()
    {
        $updates = Telegram::getUpdates();
        return response()->json($updates);
    }

    // Xabar yuborish (test uchun)
    public function sendMessage()
    {
        $chatIds = explode(',', env('TELEGRAM_CHAT_ID')); // Chat ID larni vergul bo'yicha ajratamiz

        foreach ($chatIds as $chatId) {
            $chatId = (int) trim($chatId); // Chat ID ni integerga o'zgartiramiz va bo'sh joylardan tozalaymiz

            try {
                Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => 'Salom! in Laravel Telegram botay.',
                    'parse_mode' => 'HTML',
                ]);
            } catch (\Telegram\Bot\Exceptions\TelegramResponseException $e) {
                return response()->json(['error' => 'Bot chatda topilmadi: ' . $e->getMessage()]);
            }
        }

        return response()->json(['message' => 'Xabar royi shid']);
    }
}
