<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetTenantMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $slug = $request->route('tenant');

        if (!$slug) {
            abort(404);
        }

        $tenant = Tenant::where('slug', $slug)->where('ativo', true)->firstOrFail();

        // Disponibiliza o tenant globalmente no container para o TenantScope
        app()->instance('current_tenant', $tenant);

        // Também guarda na request para os controllers acessarem facilmente
        $request->attributes->set('tenant', $tenant);

        return $next($request);
    }
}
