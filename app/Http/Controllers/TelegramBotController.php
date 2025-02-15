<?php

namespace App\Http\Controllers;

use App\Models\Chanels;
use App\Models\Language;
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
            ['name' => 'Uzbek Movies', 'username' => 'barnomahoyi_tojiki'],
            ['name' => 'Premiere Movies', 'username' => 'barnomahoyi_tojiki'],
        ],
        'ru' => [
            ['name' => 'Russian Movies', 'username' => 'barnomahoyi_tojiki'],
            ['name' => 'Premiere Cinema', 'username' => 'barnomahoyi_tojiki'],
        ],
        'tj' => [
            ['name' => 'Tajik Movies', 'username' => 'barnomahoyi_tojiki'],
            ['name' => 'New Cinema', 'username' => 'barnomahoyi_tojiki'],
        ],
        'en' => [
            ['name' => 'English Movies', 'username' => 'barnomahoyi_tojiki'],
            ['name' => 'Premiere Cinema', 'username' => 'barnomahoyi_tojiki'],
        ],
    ];

    public function __construct()
    {
        $this->telegram = new Api(env('TELEGRAM_BOT_TOKEN'));

         // chanel from database
        $this->requiredChannels = $this->getChannelsFromDatabase();
    }


    private function getChannelsFromDatabase()
    {
        $channels = Chanels::all();

        $groupedChannels = [];
        foreach ($channels as $channel) {
            $groupedChannels[$channel->language_code][] = [
                'name' => $channel->name,
                'username' => $channel->username,
            ];
        }

        return $groupedChannels;
    }

  public function handleWebhook()
{
    $update = $this->telegram->getWebhookUpdate();

    if ($update->isType('message')) {
        $message = $update->getMessage();
        $chat_id = $message->getChat()->getId();
        $text = $message->getText();

        // Check if the user has previously selected a language
        $user = Language::where('telegram_id', $chat_id)->first();

        if ($text === '/start') {
            $this->deletePreviousMessage($chat_id); // Delete the previous message

            if ($user) {
                // Foydalanuvchi tilni tanlagan, lekin kanalga a'zo bo'lganligini tekshirish
                if (!$this->checkUserSubscribed($chat_id)) {
                    return $this->askToJoinChannels($chat_id, $user->language);
                }
                return $this->showMainMenu($chat_id, $user->language);
            }
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
        $message_id = $callback->getMessage()->getMessageId(); // Old message's message_id
        $data = $callback->getData();

        if (strpos($data, 'lang_') === 0) {
            return $this->setUserLanguage($chat_id, str_replace('lang_', '', $data));
        } elseif ($data === 'search_movie') {
            return $this->askForMovieCode($chat_id, $message_id);
        } elseif ($data === 'list_movies') {
            return $this->showMoviesList($chat_id, $message_id);
        } elseif (strpos($data, 'list_movies_') === 0) {
            $page = (int)str_replace('list_movies_', '', $data); // Get the page number
            return $this->showMoviesList($chat_id, $message_id, $page);
        } elseif (strpos($data, 'movie_') === 0) {
            return $this->sendMovieByCode($chat_id, str_replace('movie_', '', $data));
        } elseif ($data === 'back_main_menu') {
            return $this->showMainMenu($chat_id, $message_id);
        } elseif ($data === 'check_subscription') {
            return $this->checkUserSubscribed($chat_id) ? $this->showMainMenu($chat_id, $message_id) : $this->askToJoinChannels($chat_id, Cache::get("user_lang_$chat_id", 'ru'));
        } elseif ($data === 'change_language') {
            return $this->sendLanguageSelection($chat_id);
        }
    }

    return response()->json(['status' => 'no update']);
}

    private function deletePreviousMessage($chat_id)
    {
        $message_id = Cache::get("last_message_$chat_id");

        \Log::info("Attempting to delete message: Chat ID - $chat_id, Message ID - $message_id");

        if ($message_id) {
            try {
                $this->telegram->deleteMessage([
                    'chat_id' => $chat_id,
                    'message_id' => $message_id
                ]);

                \Log::info("Message deleted: Message ID - $message_id");

            } catch (\Exception $e) {
                \Log::error("Error deleting message: " . $e->getMessage());
            }
        } else {
            \Log::info("No message found to delete.");
        }
    }

    private function sendLanguageSelection($chat_id)
    {
        $this->deletePreviousMessage($chat_id); // Delete the previous message

        $keyboard = Keyboard::make()->inline();
        $keyboard->row([
            Keyboard::button(['text' => '🇷🇺 Russian', 'callback_data' => 'lang_ru']),
            Keyboard::button(['text' => '🇹🇯 Tajik', 'callback_data' => 'lang_tj']),
            Keyboard::button(['text' => '🇺🇿 Uzbek', 'callback_data' => 'lang_uz']),
            Keyboard::button(['text' => '🇺🇸 English', 'callback_data' => 'lang_en']),
        ]);

        $response = $this->telegram->sendMessage([
            'chat_id' => $chat_id,
            'text' => "🌍 Choose your language:\n\n🇷🇺 Выберите язык\n🇹🇯 Забони худро интихоб кунед\n🇺🇿 Tilni tanlang\n🇺🇸 Choose your language",
            'reply_markup' => $keyboard,
        ]);

        // Save the new message's message_id to cache
        Cache::put("last_message_$chat_id", $response->getMessageId(), 3600);
    }

    private function setUserLanguage($chat_id, $language)
    {
        // Save the user's language to the database or cache
        Language::updateOrCreate(
            ['telegram_id' => $chat_id],
            ['language' => $language]
        );

        Cache::put("user_lang_$chat_id", $language, 3600);

        if (!$this->checkUserSubscribed($chat_id)) {
            return $this->askToJoinChannels($chat_id, $language);
        }

        return $this->showMainMenu($chat_id);
    }

  private function showMainMenu($chat_id, $message_id = null)
{
    $this->deletePreviousMessage($chat_id); // Delete the previous message

    $language = Cache::get("user_lang_$chat_id", 'ru');

    $messages = [
        'uz' => "🏠 Main menu:\n\n🎬 You can enter a movie code or choose from the list.",
        'ru' => "🏠 Главное меню:\n\n🎬 Вы можете ввести код фильма или выбрать из списка.",
        'tj' => "🏠 Менюи асосӣ:\n\n🎬 Шумо метавонед рамзи филмро ворид кунед ё аз рӯйхат интихоб кунед.",
        'en' => "🏠 Main menu:\n\n🎬 You can enter a movie code or choose from the list.",
    ];

    $text = $messages[$language] ?? $messages['en']; // Default - English

    $keyboard = Keyboard::make()->inline()
        ->row([
            Keyboard::button([
                'text' => ($language == 'uz' ? '🔍 Search by movie code' :
                          ($language == 'tj' ? '🔍 Ҷустуҷӯ аз рӯи рамз' :
                          ($language == 'ru' ? '🔍 Найти по коду' : '🔍 Search by code'))),
                'callback_data' => 'search_movie'
            ]),
            Keyboard::button([
                'text' => ($language == 'uz' ? '📂 Movie list' :
                          ($language == 'tj' ? '📂 Рӯйхати филмҳо' :
                          ($language == 'ru' ? '📂 Список фильмов' : '📂 Movie list'))),
                'callback_data' => 'list_movies'
            ]),
        ]);

    try {
        if ($message_id) {
            // Update the old menu
            $response = $this->telegram->editMessageText([
                'chat_id' => $chat_id,
                'message_id' => $message_id,
                'text' => $text,
                'reply_markup' => $keyboard,
            ]);
        } else {
            // Send a new menu
            $response = $this->telegram->sendMessage([
                'chat_id' => $chat_id,
                'text' => $text,
                'reply_markup' => $keyboard,
            ]);
        }

        // Save the new message's message_id to cache
        Cache::put("last_message_$chat_id", $response->getMessageId(), 3600);

    } catch (\Exception $e) {
        \Log::error("Error updating message: " . $e->getMessage());

        // If the message is not found, send a new message
        $response = $this->telegram->sendMessage([
            'chat_id' => $chat_id,
            'text' => $text,
            'reply_markup' => $keyboard,
        ]);

        // Save the new message's message_id to cache
        Cache::put("last_message_$chat_id", $response->getMessageId(), 3600);
    }
}

   private function askToJoinChannels($chat_id, $language, $message_id = null)
{
    $this->deletePreviousMessage($chat_id); // Delete the previous message

    // Bazadan kanallarni olish
    $channels = $this->requiredChannels[$language] ?? $this->requiredChannels['ru'];

    // Translations for each language
    $translations = [
        'uz' => [
            'header' => "🔔 To use the bot, please join the following channels:\n\n",
            'join' => "📢 Join channel",
            'subscribed' => "✅ I have subscribed",
            'back' => "🔙 Back",
        ],
        'ru' => [
            'header' => "🔔 Для использования бота подпишитесь на следующие каналы:\n\n",
            'join' => "📢 Подписаться на канал",
            'subscribed' => "✅ Я подписался",
            'back' => "🔙 Назад",
        ],
        'tj' => [
            'header' => "🔔 Барои истифодаи бот ба каналҳои зерин обуна шавед:\n\n",
            'join' => "📢 Обуна шудан ба канал",
            'subscribed' => "✅ Ман обуна шудам",
            'back' => "🔙 Бозгашт",
        ],
        'en' => [
            'header' => "🔔 To use the bot, please join the following channels:\n\n",
            'join' => "📢 Join channel",
            'subscribed' => "✅ I have subscribed",
            'back' => "🔙 Back",
        ],
    ];

    // Get texts based on the user's selected language
    $texts = $translations[$language] ?? $translations['en'];

    $text = $texts['header'];
    $keyboard = Keyboard::make()->inline();

    foreach ($channels as $channel) {
        $text .= "👉 {$channel['name']}\n";

        // Add a button for each channel
        $keyboard->row([
            Keyboard::button([
                'text' => "{$texts['join']} ({$channel['name']})",
                'url' => "https://t.me/{$channel['username']}"
            ])
        ]);
    }

    // "✅ I have subscribed" button
    $keyboard->row([
        Keyboard::button([
            'text' => $texts['subscribed'],
            'callback_data' => "check_subscription"
        ])
    ]);

    // "🔙 Back" button (to return to language selection)
    $keyboard->row([
        Keyboard::button([
            'text' => $texts['back'],
            'callback_data' => "change_language"
        ])
    ]);

    try {
        if ($message_id) {
            // Update the old menu
            $response = $this->telegram->editMessageText([
                'chat_id' => $chat_id,
                'message_id' => $message_id,
                'text' => $text,
                'parse_mode' => 'HTML',
                'reply_markup' => $keyboard,
            ]);
        } else {
            // Send a new menu
            $response = $this->telegram->sendMessage([
                'chat_id' => $chat_id,
                'text' => $text,
                'parse_mode' => 'HTML',
                'reply_markup' => $keyboard,
            ]);
        }

        // Save the new message's message_id to cache
        Cache::put("last_message_$chat_id", $response->getMessageId(), 3600);

    } catch (\Exception $e) {
        \Log::error("Error updating message: " . $e->getMessage());

        // If the message is not found, send a new message
        $response = $this->telegram->sendMessage([
            'chat_id' => $chat_id,
            'text' => $text,
            'parse_mode' => 'HTML',
            'reply_markup' => $keyboard,
        ]);

        // Save the new message's message_id to cache
        Cache::put("last_message_$chat_id", $response->getMessageId(), 3600);
    }
}

    private function checkUserSubscribed($chat_id)
    {
        $language = Cache::get("user_lang_$chat_id", 'ru'); // Get the user's language
        $channels = $this->requiredChannels[$language] ?? $this->requiredChannels['ru'];

        // Translations for each language
        $translations = [
            'uz' => [
                'not_subscribed' => "❌ You have not yet joined the channel: :channel",
                'error' => "⚠️ An error occurred while checking subscription: :channel"
            ],
            'ru' => [
                'not_subscribed' => "❌ Вы еще не подписались на канал: :channel",
                'error' => "⚠️ Произошла ошибка при проверке подписки: :channel"
            ],
            'tj' => [
                'not_subscribed' => "❌ Шумо ҳоло ба канал аъзо нашудаед: :channel",
                'error' => "⚠️ Ҳангоми тафтишоти обуна хатогӣ рух дод: :channel"
            ],
            'en' => [
                'not_subscribed' => "❌ You have not yet joined the channel: :channel",
                'error' => "⚠️ An error occurred while checking subscription: :channel"
            ],
        ];

        // Default language is English
        $texts = $translations[$language] ?? $translations['en'];

        foreach ($channels as $channel) {
            try {
                $chatMember = $this->telegram->getChatMember([
                    'chat_id' => "@{$channel['username']}", // Channel must start with @
                    'user_id' => $chat_id,
                ]);

                if (!isset($chatMember['status'])) {
                    \Log::warning("User status not determined: " . json_encode($chatMember));
                    return false;
                }

                if (!in_array($chatMember['status'], ['member', 'administrator', 'creator'])) {
                    \Log::info("User {$chat_id} is not a member of the channel: {$channel['username']}");

                    // Send a message in the user's language if not subscribed
                    $message = str_replace(':channel', $channel['name'], $texts['not_subscribed']);
                    $this->telegram->sendMessage([
                        'chat_id' => $chat_id,
                        'text' => $message,
                    ]);

                    return false;
                }
            } catch (\Exception $e) {
                \Log::error("Error checking channel subscription ({$channel['username']}): " . $e->getMessage());

                // If an error occurs, send a message in the user's language
                $message = str_replace(':channel', $channel['name'], $texts['error']);
                $this->telegram->sendMessage([
                    'chat_id' => $chat_id,
                    'text' => $message,
                ]);

                return false;
            }
        }

        return true; // If subscribed to all channels
    }


private function showMoviesList($chat_id, $message_id = null, $page = 1)
{
    $this->deletePreviousMessage($chat_id); // Oldingi xabarni o'chirish

    // Foydalanuvchi tanlagan tilni olish
    $user = Language::where('telegram_id', $chat_id)->first();
    $language = $user->language ?? 'en'; // Default English

    $perPage = 10;
    $offset = ($page - 1) * $perPage;
    $totalMovies = Movie::where('language_code', $language)->count();
    $movies = Movie::where('language_code', $language)->skip($offset)->take($perPage)->get();

    // Agar kinolar ro'yxati bo'sh bo'lsa
    if ($movies->isEmpty()) {
        // Tilga mos xabar yuborish
        $messages = [
            'uz' => "📂 Siz tanlagan tilda kinolar mavjud emas.",
            'ru' => "📂 На выбранном языке фильмы отсутствуют.",
            'tj' => "📂 Дар забони интихобшуда филмҳо мавҷуд нестанд.",
            'en' => "📂 No movies available in your selected language.",
        ];

        $text = $messages[$language] ?? $messages['en']; // Default English

        // Xabar yuborish
        $this->telegram->sendMessage([
            'chat_id' => $chat_id,
            'text' => $text,
        ]);

        // Asosiy menyuni qayta ko'rsatish
        return $this->showMainMenu($chat_id);
    }

    // Kinolar ro'yxatini yuborish
    $keyboard = Keyboard::make()->inline();
    foreach ($movies as $movie) {
        $keyboard->row([
            Keyboard::button([
                'text' => "🎬 " . $movie->file_name,
                'callback_data' => "movie_{$movie->code}",
            ])
        ]);
    }

    // Pagination buttons
    $paginationButtons = [];
    if ($page > 1) {
        $paginationButtons[] = Keyboard::button([
            'text' => "⬅️ Previous",
            'callback_data' => "list_movies_" . ($page - 1)
        ]);
    }
    if ($offset + $perPage < $totalMovies) {
        $paginationButtons[] = Keyboard::button([
            'text' => "➡️ Next",
            'callback_data' => "list_movies_" . ($page + 1)
        ]);
    }

    if (!empty($paginationButtons)) {
        $keyboard->row($paginationButtons);
    }

    // "🔙 Back" button
    $keyboard->row([
        Keyboard::button([
            'text' => "🔙 Back",
            'callback_data' => "back_main_menu"
        ])
    ]);

    try {
        if ($message_id) {
            // Update existing message
            $response = $this->telegram->editMessageText([
                'chat_id' => $chat_id,
                'message_id' => $message_id,
                'text' => "🎞 Movie list (Page: $page) - Language: $language",
                'reply_markup' => $keyboard,
            ]);
        } else {
            // Send a new message
            $response = $this->telegram->sendMessage([
                'chat_id' => $chat_id,
                'text' => "🎞 Movie list (Page: $page) - Language: $language",
                'reply_markup' => $keyboard,
            ]);
        }

        // Save the new message's message_id to cache
        Cache::put("last_message_$chat_id", $response->getMessageId(), 3600);

    } catch (\Exception $e) {
        \Log::error("Error updating message: " . $e->getMessage());

        // If the message is not found, send a new message
        $response = $this->telegram->sendMessage([
            'chat_id' => $chat_id,
            'text' => "🎞 Movie list (Page: $page) - Language: $language",
            'reply_markup' => $keyboard,
        ]);

        // Save the new message's message_id to cache
        Cache::put("last_message_$chat_id", $response->getMessageId(), 3600);
    }
}


  private function askForMovieCode($chat_id, $message_id = null)
{
    $this->deletePreviousMessage($chat_id); // Delete the previous message

    // Get the user's selected language
    $language = Cache::get("user_lang_$chat_id", 'en');

    // Translations for each language
    $translations = [
        'uz' => "🎬 Kino kodini yuboring:",
        'ru' => "🎬 Отправьте код фильма:",
        'tj' => "🎬 Рамзи филмро фиристед:",
        'en' => "🎬 Send the movie code:",
    ];

    // Default to English if the language is not found
    $text = $translations[$language] ?? $translations['en'];

    $keyboard = Keyboard::make()->inline()->row([
        Keyboard::button([
            'text' => $language === 'ru' ? "🔙 Назад" : ($language === 'tj' ? "🔙 Қафо" : ($language === 'uz' ? "🔙 Orqaga" : "🔙 Back")),
            'callback_data' => "back_main_menu"
        ])
    ]);

    try {
        if ($message_id) {
            // Update the old menu
            $response = $this->telegram->editMessageText([
                'chat_id' => $chat_id,
                'message_id' => $message_id,
                'text' => $text,
                'reply_markup' => $keyboard,
            ]);
        } else {
            // Send a new menu
            $response = $this->telegram->sendMessage([
                'chat_id' => $chat_id,
                'text' => $text,
                'reply_markup' => $keyboard,
            ]);
        }

        // Save the new message's message_id to cache
        Cache::put("last_message_$chat_id", $response->getMessageId(), 3600);

    } catch (\Exception $e) {
        \Log::error("Error updating message: " . $e->getMessage());

        // If the message is not found, send a new message
        $response = $this->telegram->sendMessage([
            'chat_id' => $chat_id,
            'text' => $text,
            'reply_markup' => $keyboard,
        ]);

        // Save the new message's message_id to cache
        Cache::put("last_message_$chat_id", $response->getMessageId(), 3600);
    }

    // Set a flag in cache to indicate that the bot is waiting for a movie code
    Cache::put("waiting_for_movie_code_$chat_id", true, 300);
}

private function sendMovieByCode($chat_id, $code)
{
    $this->deletePreviousMessage($chat_id); // Delete the previous message

    // Get the user's selected language
    $language = Cache::get("user_lang_$chat_id", 'en');

    // Translations for each language
    $translations = [
        'uz' => [
            'movie_found' => "🎬 Movie: :file_name\n\n📝 Code: :code\n\n👉 Full information about the movie: :description",
            'no_file' => "❌ Movie file not found.",
            'not_found' => "❌ Movie not found. Please check the movie code.",
            'retry' => "❌ Movie not found. Please send the correct movie code.",
        ],
        'ru' => [
            'movie_found' => "🎬 Фильм: :file_name\n\n📝 Код: :code\n\n👉 Полная информация о фильме: :description",
            'no_file' => "❌ Файл фильма не найден.",
            'not_found' => "❌ Фильм не найден. Пожалуйста, проверьте код фильма.",
            'retry' => "❌ Фильм не найден. Пожалуйста, отправьте правильный код фильма.",
        ],
        'tj' => [
            'movie_found' => "🎬 Филм: :file_name\n\n📝 Рамз: :code\n\n👉 Маълумоти пурра дар бораи филм: :description",
            'no_file' => "❌ Файли филм мавҷуд нест.",
            'not_found' => "❌ Филм ёфт нашуд. Лутфан рамзи филмро тафтиш кунед.",
            'retry' => "❌ Филм ёфт нашуд. Лутфан рамзи дурусти филмро фиристед.",
        ],
        'en' => [
            'movie_found' => "🎬 Movie: :file_name\n\n📝 Code: :code\n\n👉 Full information about the movie: :description",
            'no_file' => "❌ Movie file not found.",
            'not_found' => "❌ Movie not found. Please check the movie code.",
            'retry' => "❌ Movie not found. Please send the correct movie code.",
        ],
    ];

    // Default to English if the language is not found
    $texts = $translations[$language] ?? $translations['en'];

    // Find the movie by code
    $movie = Movie::where('code', $code)->first();

    if ($movie) {
        // Send information about the movie
        $message = str_replace(
            [':file_name', ':code', ':description'],
            [$movie->file_name, $movie->code, $movie->description],
            $texts['movie_found']
        );

        $this->telegram->sendMessage([
            'chat_id' => $chat_id,
            'text' => $message,
        ]);

        // If file_id exists, send the video
        if (!empty($movie->file_id)) {
            $this->telegram->sendVideo([
                'chat_id' => $chat_id,
                'video' => $movie->file_id, // Video ID saved on Telegram server
                'caption' => "🎬 {$movie->file_name}"
            ]);
        } else {
            $this->telegram->sendMessage([
                'chat_id' => $chat_id,
                'text' => $texts['no_file'],
            ]);
        }
    } else {
        // Movie not found
        $this->telegram->sendMessage([
            'chat_id' => $chat_id,
            'text' => $texts['not_found'],
        ]);

        // Ask the user to send the movie code again
        $this->askForMovieCode($chat_id);
    }
}
}
