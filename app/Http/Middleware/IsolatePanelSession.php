<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IsolatePanelSession
{
    public function handle(Request $request, Closure $next, string $currentGuard, string $otherGuard): Response
    {
        if (auth($otherGuard)->check()) {
            auth($otherGuard)->logout();
        }

        return $next($request);
    }
}
