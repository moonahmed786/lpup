<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UseApiTokenCookie
{
    public function handle(Request $request, Closure $next): Response
    {
        $cookieName = config('api.auth_cookie.name');
        $token = $request->cookie($cookieName);

        if (! $request->bearerToken() && is_string($token) && $token !== '') {
            $request->headers->set('Authorization', 'Bearer '.$token);
        }

        return $next($request);
    }
}
