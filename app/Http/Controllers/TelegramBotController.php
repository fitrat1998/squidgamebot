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
            ['name' => 'O‘zbek Filmlar', 'username' => '@barnomahoyi_tojiki'],
            ['name' => 'Premyera Kinolar', 'username' => '@barnomahoyi_tojiki'],
        ],
        'ru' => [
            ['name' => 'Русские фильмы', 'username' => '@barnomahoyi_tojiki'],
            ['name' => 'Премьера кино', 'username' => '@barnomahoyi_tojiki'],
        ],
        'tj' => [
            ['name' => 'Тоҷик Филмҳо', 'username' => '@barnomahoyi_tojiki'],
            ['name' => 'Нав Кино', 'username' => '@barnomahoyi_tojiki'],
        ],
    ];

    public function __construct()
    {
        $this->telegram = new Api(env('TELEGRAM_BOT_TOKEN'));
    }

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
        }

        if ($update->isType('callback_query')) {
            $callback = $update->getCallbackQuery();
            $chat_id = $callback->getMessage()->getChat()->getId();
            $data = $callback->getData();

            if (strpos($data, 'lang_') === 0) {
                return $this->setUserLanguage($chat_id, str_replace('lang_', '', $data));
            } elseif ($data === 'check_subscription') {
                return $this->checkSubscriptionStatus($chat_id);
            }
        }

        return response()->json(['status' => 'no update']);
    }

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

    private function setUserLanguage($chat_id, $language)
    {
        session()->put("user_lang_$chat_id", $language);

        if (!$this->checkUserSubscribed($chat_id)) {
            return $this->askToJoinChannels($chat_id, $language);
        }

        return $this->showMoviesList($chat_id, $language);
    }

    private function checkUserSubscribed($chat_id)
    {
        $language = session()->get("user_lang_$chat_id", 'ru');
        $channels = $this->requiredChannels[$language] ?? $this->requiredChannels['ru'];

        foreach ($channels as $channel) {
            try {
                $chatMember = $this->telegram->getChatMember([
                    'chat_id' => $channel['username'],
                    'user_id' => $chat_id,
                ]);

                if (!isset($chatMember['status']) || !in_array($chatMember['status'], ['member', 'administrator', 'creator'])) {
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
        $channels = $this->requiredChannels[$language] ?? $this->requiredChannels['ru'];
        $keyboard = Keyboard::make()->inline();

        foreach ($channels as $channel) {
            $keyboard->row([
                Button::make([
                    'text' => "➕ " . $channel['name'],
                    'url' => "https://t.me/" . ltrim($channel['username'], '@'),
                ])
            ]);
        }

        $keyboard->row([
            Button::make([
                'text' => '✅ Tasdiqlash',
                'callback_data' => 'check_subscription',
            ])
        ]);

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

    private function checkSubscriptionStatus($chat_id)
    {
        $language = session()->get("user_lang_$chat_id", 'ru');

        if ($this->checkUserSubscribed($chat_id)) {
            return $this->showMoviesList($chat_id, $language);
        } else {
            return $this->askToJoinChannels($chat_id, $language);
        }
    }

    private function sendMovieLink($chat_id, $movie_code)
    {
        $movie = \App\Models\Movie::where('code', $movie_code)->first();

        if ($movie) {
            $this->telegram->sendMessage([
                'chat_id' => $chat_id,
                'text' => "🎬 *{$movie->title}* \n📽 Havola: [Tomosha qilish]({$movie->link})",
                'parse_mode' => 'Markdown',
            ]);
        } else {
            $this->telegram->sendMessage([
                'chat_id' => $chat_id,
                'text' => "❌ Bunday kodga mos kino topilmadi!",
            ]);
        }
    }

}
