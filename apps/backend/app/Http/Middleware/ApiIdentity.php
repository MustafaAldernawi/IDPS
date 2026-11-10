<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class ApiIdentity
{
    public function handle(Request $request, Closure $next): Response
    {
        $bearer = $request->bearerToken();
        if (! $bearer && app()->environment('testing') && $request->user()) {
            return $next($request);
        }
        abort_unless($bearer, 401);
        $token = DB::table('api_tokens')->where('token_hash', hash('sha256', $bearer))->where('expires_at', '>', now())->first();
        $user = $token ? User::find($token->user_id) : null;
        abort_unless($user && $user->active, 401);
        $request->setUserResolver(fn () => $user);
        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
