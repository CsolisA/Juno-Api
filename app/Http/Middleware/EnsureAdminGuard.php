<?php

namespace App\Http\Middleware;

use App\Models\AdminUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminGuard
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user() instanceof AdminUser, 403);

        return $next($request);
    }
}
