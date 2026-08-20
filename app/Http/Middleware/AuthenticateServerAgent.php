<?php

namespace App\Http\Middleware;

use App\Models\Server;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Аутентификация агента по Bearer-токену сервера.
 */
class AuthenticateServerAgent
{
    public function handle(Request $request, Closure $next): Response
    {
        $plain = $request->bearerToken();

        if (! is_string($plain) || $plain === '') {
            abort(Response::HTTP_UNAUTHORIZED, 'Требуется токен агента.');
        }

        $server = Server::findByPlainToken($plain);

        if ($server === null || ! $server->is_active) {
            abort(Response::HTTP_UNAUTHORIZED, 'Недействительный токен агента.');
        }

        $request->attributes->set('monitoredServer', $server);

        return $next($request);
    }
}
