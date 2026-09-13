<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;

class Authenticate extends Middleware
{
    /**
     * Для API-запросов возвращаем null → middleware отдаст JSON 401,
     * а не будет пытаться редиректить на web-роут "login".
     */
    protected function redirectTo($request)
    {
        return null;
    }
}