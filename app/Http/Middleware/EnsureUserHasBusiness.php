<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasBusiness
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->business_id, 403, 'Tu usuario no está asociado a un lavadero.');

        return $next($request);
    }
}
