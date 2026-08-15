<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForceAdminLocale
{
    /**
     * Keep the administration panel consistently Arabic, regardless of a
     * stale production configuration cache or the locale used by API routes.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $previousLocale = app()->getLocale();

        app()->setLocale('ar');

        try {
            return $next($request);
        } finally {
            app()->setLocale($previousLocale);
        }
    }
}
