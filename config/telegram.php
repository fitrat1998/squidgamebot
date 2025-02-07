<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Telegram Bot API Token
    |--------------------------------------------------------------------------
    |
    | Bu yerda siz o'z botingiz uchun Telegram API tokenini qo'shishingiz kerak.
    | Bu tokenni @BotFather orqali yaratishingiz mumkin.
    |
    */

    'bot_token' => env('TELEGRAM_BOT_TOKEN', ''),

    /*
    |--------------------------------------------------------------------------
    | Default Parse Mode
    |--------------------------------------------------------------------------
    |
    | Xabarlar qanday formatda yuborilishini aniqlash uchun ishlatiladi.
    | "Markdown", "HTML" yoki "MarkdownV2" dan birini tanlashingiz mumkin.
    |
    */

    'parse_mode' => env('TELEGRAM_PARSE_MODE', 'HTML'),

    /*
    |--------------------------------------------------------------------------
    | Webhook URL
    |--------------------------------------------------------------------------
    |
    | Agar siz webhook'ni yoqmoqchi bo'lsangiz, bu yerda webhook URL'ni
    | belgilashingiz mumkin. HTTPS bo'lishi shart.
    |
    */

    'webhook_url' => env('TELEGRAM_WEBHOOK_URL', ''),

    /*
    |--------------------------------------------------------------------------
    | Webhook Secret Token (Optional)
    |--------------------------------------------------------------------------
    |
    | Agar siz webhook'dan foydalanayotgan bo'lsangiz, xavfsizlik uchun
    | maxsus secret token belgilashingiz mumkin.
    |
    */

    'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET', ''),

    /*
    |--------------------------------------------------------------------------
    | Bot Default Chat ID
    |--------------------------------------------------------------------------
    |
    | Agar bot faqat bitta chat bilan ishlasa, bu yerda standart chat ID
    | ni belgilashingiz mumkin.
    |
    */

    'default_chat_id' => env('TELEGRAM_CHAT_ID', ''),

    /*
    |--------------------------------------------------------------------------
    | API Timeout
    |--------------------------------------------------------------------------
    |
    | API so'rovlarining maksimal kutish vaqtini belgilash (soniyalarda).
    |
    */

    'timeout' => env('TELEGRAM_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Agar bot so'rovlarini log faylga yozishni istasangiz, bu opsiyani yoqishingiz mumkin.
    |
    */

    'logging' => env('TELEGRAM_LOGGING', false),
];
