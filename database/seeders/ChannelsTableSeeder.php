<?php

namespace Database\Seeders;

use App\Models\Chanels;
use Illuminate\Database\Seeder;

class ChannelsTableSeeder extends Seeder
{
    public function run()
    {
        $channels = [
            ['language_code' => 'uz', 'name' => 'O‘zbek Filmlar', 'username' => 'barnomahoyi_tojiki'],
            ['language_code' => 'ru', 'name' => 'Русские фильмы', 'username' => 'barnomahoyi_tojiki'],
            ['language_code' => 'tj', 'name' => 'Тоҷик Филмҳо', 'username' => 'barnomahoyi_tojiki'],
            ['language_code' => 'en', 'name' => 'English Movies', 'username' => 'barnomahoyi_tojiki'],
        ];

        foreach ($channels as $channel) {
            Chanels::create($channel);
        }
    }
}
