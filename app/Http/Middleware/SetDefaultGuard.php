<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetDefaultGuard
{
    public function handle(Request $request, Closure $next, string $guard): Response
    {
        auth()->shouldUse($guard);

        if (! app()->bound('current_tenant') && auth()->check()) {
            $tenantId = auth()->user()?->tenant_id;
            if ($tenantId) {
                $tenant = Tenant::withoutGlobalScopes()->find($tenantId);
                if ($tenant && $tenant->ativo) {
                    app()->instance('current_tenant', $tenant);
                } else {
                    auth()->logout();
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();
                    return redirect('/admin/login');
                }
            }
        }

        return $next($request);
    }
}
