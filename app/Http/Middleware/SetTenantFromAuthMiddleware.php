<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Support\Locale;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetTenantFromAuthMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('admin');

        if ($user && $user->tenant_id) {
            $tenant = Tenant::find($user->tenant_id);
            if ($tenant && $tenant->ativo) {
                app()->instance('current_tenant', $tenant);
                // Painel também segue o idioma do estabelecimento
                Locale::aplicarDoTenant();
            }
        }

        return $next($request);
    }
}
