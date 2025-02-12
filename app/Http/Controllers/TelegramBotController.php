<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Telegram\Bot\Api;
use Telegram\Bot\Keyboard\Keyboard;
use App\Models\Movie;
use Illuminate\Support\Facades\Cache;

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

            if (Cache::has("waiting_for_movie_code_$chat_id")) {
                Cache::forget("waiting_for_movie_code_$chat_id");
                return $this->sendMovieByCode($chat_id, $text);
            }
        }

        if ($update->isType('callback_query')) {
            $callback = $update->getCallbackQuery();
            $chat_id = $callback->getMessage()->getChat()->getId();
            $data = $callback->getData();

            if (strpos($data, 'lang_') === 0) {
                return $this->setUserLanguage($chat_id, str_replace('lang_', '', $data));
            } elseif ($data === 'search_movie') {
                return $this->askForMovieCode($chat_id);
            } elseif ($data === 'list_movies') {
                return $this->showMoviesList($chat_id);
            } elseif (strpos($data, 'movie_') === 0) {
                return $this->sendMovieByCode($chat_id, str_replace('movie_', '', $data));
            }
        }

        return response()->json(['status' => 'no update']);
    }

    private function sendLanguageSelection($chat_id)
    {
        $keyboard = Keyboard::make()->inline()
            ->row([
                Keyboard::button(['text' => '🇷🇺 Русский', 'callback_data' => 'lang_ru']),
                Keyboard::button(['text' => '🇹🇯 Тоҷикӣ', 'callback_data' => 'lang_tj']),
                Keyboard::button(['text' => '🇺🇿 O‘zbek', 'callback_data' => 'lang_uz']),
            ]);

        $this->telegram->sendMessage([
            'chat_id' => $chat_id,
            'text' => "Выберите язык / Tilni tanlang / Забони худро интихоб кунед:",
            'reply_markup' => $keyboard,
        ]);
    }

    private function setUserLanguage($chat_id, $language)
    {
        Cache::put("user_lang_$chat_id", $language, 3600); // Foydalanuvchi tilini keshga saqlash

        if (!$this->checkUserSubscribed($chat_id)) {
            return $this->askToJoinChannels($chat_id, $language);
        }

        return $this->showMainMenu($chat_id, $language);
    }


    private function askToJoinChannels($chat_id, $language)
    {
        $channels = $this->requiredChannels[$language] ?? $this->requiredChannels['ru'];

        $messages = [
            'uz' => "📢 Botdan foydalanish uchun quyidagi kanallarga a'zo bo‘ling:\n\n",
            'ru' => "📢 Чтобы использовать бот, подпишитесь на следующие каналы:\n\n",
            'tj' => "📢 Барои истифодаи бот, ба каналҳои зерин ҳамроҳ шавед:\n\n",
        ];

        $text = $messages[$language] ?? $messages['ru']; // Default rus tiliga

        foreach ($channels as $channel) {
            $text .= "➡️ [{$channel['name']}]({$channel['username']})\n";
        }

        $footerMessages = [
            'uz' => "\n✅ A'zo bo‘lgach, /start buyrug‘ini qayta yuboring.",
            'ru' => "\n✅ После подписки отправьте команду /start.",
            'tj' => "\n✅ Баъди ҳамроҳ шудан, фармони /start-ро ирсол кунед.",
        ];

        $text .= $footerMessages[$language] ?? $footerMessages['ru'];

        $this->telegram->sendMessage([
            'chat_id' => $chat_id,
            'text' => $text,
            'parse_mode' => 'Markdown',
        ]);
    }

    private function checkUserSubscribed($chat_id)
    {
        $language = Cache::get("user_lang_$chat_id", 'ru'); // Foydalanuvchi tilini olish
        $channels = $this->requiredChannels[$language] ?? $this->requiredChannels['ru'];

        foreach ($channels as $channel) {
            try {
                $chatMember = $this->telegram->getChatMember([
                    'chat_id' => $channel['username'],
                    'user_id' => $chat_id,
                ]);

                if (!isset($chatMember['status']) || !in_array($chatMember['status'], ['member', 'administrator', 'creator'])) {
                    return false; // Agar foydalanuvchi a'zo bo‘lmasa
                }
            } catch (\Exception $e) {
                \Log::error("Telegram API error: " . $e->getMessage());
                return false; // Xatolik bo‘lsa ham, foydalanuvchini a’zo emas deb qabul qilamiz
            }
        }

        return true; // Agar barcha kanallarga a'zo bo‘lsa
    }


    private function askForMovieCode($chat_id)
    {
        // Foydalanuvchi allaqachon kutayotgan bo‘lsa, qayta yuborilmasligi kerak
        if (Cache::has("waiting_for_movie_code_$chat_id")) {
            return;
        }

        Cache::put("waiting_for_movie_code_$chat_id", true, 600);

        $this->telegram->sendMessage([
            'chat_id' => $chat_id,
            'text' => "🎬 Kino kodini kiriting:",
        ]);
    }

    private function sendMovieByCode($chat_id, $code)
    {
        $movie = Movie::where('code', $code)->first();

        if (!$movie) {
            $this->telegram->sendMessage([
                'chat_id' => $chat_id,
                'text' => "❌ Bunday kodga mos kino topilmadi!",
            ]);
            return;
        }

        $this->telegram->sendVideo([
            'chat_id' => $chat_id,
            'video' => $movie->file_id,
            'caption' => "🎬 *{$movie->file_name}*",
            'parse_mode' => 'Markdown',
        ]);
    }

    private function showMoviesList($chat_id)
    {
        $movies = Movie::all();
        if ($movies->isEmpty()) {
            $this->telegram->sendMessage([
                'chat_id' => $chat_id,
                'text' => "📂 Hozircha kinolar mavjud emas.",
            ]);
            return;
        }

        $keyboard = Keyboard::make()->inline();
        foreach ($movies as $movie) {
            $keyboard->row([
                Keyboard::button([
                    'text' => "🎬 " . $movie->file_name,
                    'callback_data' => "movie_{$movie->code}",
                ])
            ]);
        }

        $this->telegram->sendMessage([
            'chat_id' => $chat_id,
            'text' => "🎞 Kinolar ro‘yxati:",
            'reply_markup' => $keyboard,
        ]);
    }
}
