<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Telegram\Bot\Api;
use Telegram\Bot\Keyboard\Keyboard;
use Telegram\Bot\Keyboard\Button;

class TelegramBotController extends Controller
{
    protected $telegram;
    protected $requiredChannels = [
        'uz' => [
            ['name' => 'O‘zbek Filmlar', 'username' => '@mirkomil_kuhistoniy_blog'],
            ['name' => 'Premyera Kinolar', 'username' => '@mirkomil_kuhistoniy_blog'],
        ],
        'ru' => [
            ['name' => 'Русские фильмы', 'username' => '@mirkomil_kuhistoniy_blog'],
            ['name' => 'Премьера кино', 'username' => '@mirkomil_kuhistoniy_blog'],
        ],
        'tj' => [
            ['name' => 'Тоҷик Филмҳо', 'username' => '@mirkomil_kuhistoniy_blog'],
            ['name' => 'Нав Кино', 'username' => '@mirkomil_kuhistoniy_blog'],
        ],
    ];


    public function __construct()
    {
        $this->telegram = new Api(env('TELEGRAM_BOT_TOKEN'));
    }

    // 📌 Telegram webhookni qabul qilish
    public function handleWebhook()
    {
        $update = $this->telegram->getWebhookUpdate();

        if ($update->isType('message')) {
            $message = $update->getMessage();
            $chat_id = $message->getChat()->getId();
            $text = $message->getText();

            if ($text === '/start') {
                return $this->sendLanguageSelection($chat_id);
            }

            if (strpos($text, 'kino_') === 0) {
                return $this->sendMovieLink($chat_id, $text);
            }
        }

        if ($update->isType('callback_query')) {
            $callback = $update->getCallbackQuery();
            $chat_id = $callback->getMessage()->getChat()->getId();
            $data = $callback->getData();

            if (strpos($data, 'lang_') === 0) {
                return $this->setUserLanguage($chat_id, str_replace('lang_', '', $data));
            }
        }

        return response()->json(['status' => 'no update']);
    }

    // 📌 1-chi bosqich: Til tanlash menyusi
    private function sendLanguageSelection($chat_id)
    {
        $keyboard = Keyboard::make()->inline()
            ->row([
                Button::make(['text' => '🇷🇺 Русский', 'callback_data' => 'lang_ru']),
                Button::make(['text' => '🇹🇯 Тоҷикӣ', 'callback_data' => 'lang_tj']),
                Button::make(['text' => '🇺🇿 O‘zbek', 'callback_data' => 'lang_uz']),
            ]);

        $this->telegram->sendMessage([
            'chat_id' => $chat_id,
            'text' => "Выберите язык / Tilni tanlang / Забони худро интихоб кунед:",
            'reply_markup' => $keyboard,
        ]);
    }

    // 📌 2-chi bosqich: Foydalanuvchi tilni tanlagandan keyin
    private function setUserLanguage($chat_id, $language)
    {
        session()->put("user_lang_$chat_id", $language);

        // ✅ Kanalga qo‘shilganligini tekshirish
        if (!$this->checkUserSubscribed($chat_id)) {
            return $this->askToJoinChannels($chat_id, $language);
        }

        // ✅ Kino ro‘yxatini ko‘rsatish
        return $this->showMoviesList($chat_id, $language);
    }

    // 📌 Kanal a'zo bo‘lishni tekshirish
    private function checkUserSubscribed($chat_id)
    {
        foreach ($this->requiredChannels as $channel) {
            try {
                $chatMember = $this->telegram->getChatMember([
                    'chat_id' => $channel['username'],
                    'user_id' => $chat_id,
                ]);

                if (!isset($chatMember->status) || !in_array($chatMember->status, ['member', 'administrator', 'creator'])) {
                    return false;
                }
            } catch (\Exception $e) {
                \Log::error("Telegram API error: " . $e->getMessage());
                return false;
            }
        }
        return true;
    }

    private function askToJoinChannels($chat_id, $language)
    {
        $channels = $this->requiredChannels[$language] ?? $this->requiredChannels['ru']; // Standart ruscha

        $buttons = [];
        foreach ($channels as $channel) {
            $buttons[] = Button::make([
                'text' => "➕ " . $channel['name'],
                'url' => "https://t.me/" . ltrim($channel['username'], '@'),
            ]);
        }

        $keyboard = Keyboard::make()->inline()->row($buttons);

        $messages = [
            'ru' => "Чтобы использовать бот, подпишитесь на следующие каналы 👇",
            'tj' => "Барои истифодаи бот, ба каналҳои зерин обуна шавед 👇",
            'uz' => "Botdan foydalanish uchun quyidagi kanallarga qo‘shiling 👇",
        ];

        $this->telegram->sendMessage([
            'chat_id' => $chat_id,
            'text' => $messages[$language] ?? $messages['ru'],
            'reply_markup' => $keyboard,
        ]);
    }


    // 📌 4-chi bosqich: Kino ro‘yxatini chiqarish
    private function showMoviesList($chat_id, $language)
    {
        $movies = [
            'uz' => ['kino_101' => '🔹 O‘zbek Kino 1', 'kino_102' => '🔹 O‘zbek Kino 2'],
            'ru' => ['kino_201' => '🔹 Русский Фильм 1', 'kino_202' => '🔹 Русский Фильм 2'],
            'tj' => ['kino_301' => '🔹 Тоҷикӣ Филм 1', 'kino_302' => '🔹 Тоҷикӣ Филм 2'],
        ];

        $keyboard = Keyboard::make()->inline();
        foreach ($movies[$language] as $code => $name) {
            $keyboard->row([Button::make(['text' => $name, 'callback_data' => $code])]);
        }

        $messages = [
            'ru' => "Выберите фильм:",
            'tj' => "Филмро интихоб кунед:",
            'uz' => "Quyidagi filmlardan birini tanlang:",
        ];

        $this->telegram->sendMessage([
            'chat_id' => $chat_id,
            'text' => $messages[$language] ?? $messages['ru'],
            'reply_markup' => $keyboard,
        ]);
    }

    // 📌 Kino havolani yuborish
    private function sendMovieLink($chat_id, $movie_code)
    {
        $movie_links = [
            'kino_101' => 'https://example.com/uzbek-movie-1',
            'kino_102' => 'https://example.com/uzbek-movie-2',
            'kino_201' => 'https://example.com/russian-movie-1',
            'kino_202' => 'https://example.com/russian-movie-2',
            'kino_301' => 'https://example.com/tajik-movie-1',
            'kino_302' => 'https://example.com/tajik-movie-2',
        ];

        $this->telegram->sendMessage([
            'chat_id' => $chat_id,
            'text' => $movie_links[$movie_code] ?? "❌ Такого фильма нет!",
        ]);
    }
}
