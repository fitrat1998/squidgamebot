<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Movie;

class MovieSeeder extends Seeder
{
    public function run()
    {
        Movie::create(['code' => '121', 'title' => 'Kalmar oyini 1 fasl 1-qism o`zbek tilida', 'link' => 'AAMCAgADIQUABJOts8wAAwNnqdqbGuyNiR8aFHbC_n4JSfSEowACVGsAAr8KUUmdeYRYNZbBMgEAB20AAzYE']);
        Movie::create(['code' => '122', 'title' => 'Kalmar oyini 1 fasl 2-qism o`zbek tilida', 'link' => 'https://example.com/uzbek-movie-2']);
        Movie::create(['code' => '123', 'title' => 'Kalmar oyini 1 fasl 3-qism o`zbek tilida', 'link' => 'https://example.com/russian-movie-1']);
        Movie::create(['code' => '124', 'title' => 'Kalmar oyini 1 fasl 4-qism o`zbek tilida', 'link' => 'https://example.com/russian-movie-2']);
        Movie::create(['code' => '125', 'title' => 'Kalmar oyini 1 fasl 5-qism o`zbek tilida', 'link' => 'https://example.com/tajik-movie-1']);
        Movie::create(['code' => '126', 'title' => 'Kalmar oyini 1 fasl 6-qism o`zbek tilida', 'link' => 'https://example.com/tajik-movie-2']);
        Movie::create(['code' => '127', 'title' => 'Kalmar oyini 1 fasl 7-qism o`zbek tilida', 'link' => 'https://example.com/tajik-movie-2']);
        Movie::create(['code' => '128', 'title' => 'Kalmar oyini 1 fasl 8-qism o`zbek tilida', 'link' => 'https://example.com/tajik-movie-2']);
        Movie::create(['code' => '129', 'title' => 'Kalmar oyini 1 fasl 9-qism o`zbek tilida', 'link' => 'https://example.com/tajik-movie-2']);
        Movie::create(['code' => '121', 'title' => 'Kalmar oyini 2 fasl 1-qism o`zbek tilida', 'link' => 'https://example.com/uzbek-movie-1']);
        Movie::create(['code' => '122', 'title' => 'Kalmar o`yini 2 fasl 2-qism o`zbek tilida', 'link' => 'https://example.com/uzbek-movie-2']);
        Movie::create(['code' => '123', 'title' => 'Kalmar o`yini 2 fasl 3-qism o`zbek tilida', 'link' => 'https://example.com/russian-movie-1']);
        Movie::create(['code' => '124', 'title' => 'Kalmar o`yini 2 fasl 4-qism o`zbek tilida', 'link' => 'https://example.com/russian-movie-2']);
        Movie::create(['code' => '125', 'title' => 'Kalmar o`yini 2 fasl 5-qism o`zbek tilida', 'link' => 'https://example.com/tajik-movie-1']);
        Movie::create(['code' => '126', 'title' => 'Kalmar o`yini 2 fasl 6-qism o`zbek tilida', 'link' => 'https://example.com/tajik-movie-2']);
        Movie::create(['code' => '127', 'title' => 'Kalmar o`yini 2 fasl 7-qism o`zbek tilida', 'link' => 'https://example.com/tajik-movie-2']);

    }
}

