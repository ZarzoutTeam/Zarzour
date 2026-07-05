<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocaleFromHeader
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->header('Accept-Language', config('app.locale'));
        $locale = str_starts_with($locale, 'en') ? 'en' : 'ar';

        app()->setLocale($locale);

        return $next($request);
    }
}
