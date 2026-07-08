<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ClearStalePanelSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if (! $user) {
            return $next($request);
        }

        $path = trim($request->path(), '/');

        if (str_starts_with($path, 'super-admin')) {
            $canAccess = $user->role === 'super_admin';
        } elseif (str_starts_with($path, 'admin')) {
            $canAccess = in_array($user->role, ['admin', 'barbeiro']);
        } else {
            return $next($request);
        }

        if (! $canAccess) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect($request->fullUrl());
        }

        return $next($request);
    }
}
