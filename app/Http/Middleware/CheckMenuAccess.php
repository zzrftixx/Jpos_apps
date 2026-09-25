<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckMenuAccess
{
    public function handle(Request $request, Closure $next, string ...$menuKeys): Response
    {
        $user = $request->user();

        if (!$user) {
            abort(403, 'Anda tidak memiliki akses ke menu ini.');
        }

        $allKeys = [];
        foreach ($menuKeys as $mk) {
            foreach (explode(',', $mk) as $k) {
                $trimmed = trim($k);
                if ($trimmed !== '') {
                    $allKeys[] = $trimmed;
                }
            }
        }

        $hasAccess = false;
        foreach ($allKeys as $key) {
            if ($user->can_access($key)) {
                $hasAccess = true;
                break;
            }
        }

        if (!$hasAccess) {
            abort(403, 'Anda tidak memiliki akses ke menu ini.');
        }

        return $next($request);
    }
}
