<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ControlController extends Controller
{
    public function index()
    {

        $url = 'https://api.telegram.org/bot7680796249:AAF08q9rFhnBHnBR1WX6HvJTYTPYrBeuMAo/getUpdates';

        $response = file_get_contents($url);
        $data = json_decode($response, true);

        foreach ($data as $d) {
            return $d->id;
        }


    }
}
