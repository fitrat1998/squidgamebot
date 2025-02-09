<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    protected $except = [
        '/telegram/webhook', // Telegram webhook uchun CSRF himoyasini o‘chiramiz
    ];
}
