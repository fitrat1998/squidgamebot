<?php

namespace App\Http\Controllers;

use App\Models\Movie;
use Illuminate\Http\Request;

class ControlController extends Controller
{

    public function index()
    {
        $url = 'https://api.telegram.org/bot7680796249:AAF08q9rFhnBHnBR1WX6HvJTYTPYrBeuMAo/getUpdates';
        $response = file_get_contents($url);

        if ($response === false) {
            return "Xatolik: API'dan ma'lumot olinmadi.";
        }

        $data = json_decode($response, true);

        if (!isset($data['result']) || !is_array($data['result'])) {
            return "Xatolik: JSON'da kerakli 'result' maydoni yo‘q.";
        }

        $count = 0; // Nechta video bazaga yozilganini sanash uchun
        $last_movie = Movie::latest('code')->first();
        $last_code = $last_movie ? $last_movie->code : 100; // Oxirgi kodni olish

        foreach ($data['result'] as $update) {
            if (isset($update['channel_post']['video'])) {
                $video = $update['channel_post']['video'];

                $file_name = $video['file_name'] ?? null;
                $file_id = $video['file_id'] ?? null;
                $file_size = $video['file_size'] ?? null;
                $mime_type = $video['mime_type'] ?? null;

                if ($file_name && $file_id && $file_size && $mime_type) {
                    // Agar bu fayl allaqachon bazada bo'lsa, yozmaymiz
                    $exists = Movie::where('file_id', $file_id)->exists();
                    if (!$exists) {
                        Movie::create([
                            'code' => ++$last_code, // Kodni oshirib borish
                            'file_name' => $file_name,
                            'file_id' => $file_id,
                            'file_size' => $file_size,
                            'mime_type' => $mime_type,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                        $count++;
                    }
                }
            }
        }

        return $count > 0 ? "$count ta yangi video bazaga yozildi!" : "Hech qanday yangi video topilmadi.";
    }


}
