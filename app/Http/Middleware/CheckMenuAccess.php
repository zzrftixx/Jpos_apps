<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckMenuAccess
{
    public function handle(Request $request, Closure $next, string $menuKey): Response
    {
        $user = $request->user();

        if (!$user || !$user->can_access($menuKey)) {
            abort(403, 'Anda tidak memiliki akses ke menu ini.');
        }

        return $next($request);
    }
}
