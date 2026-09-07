<?php

namespace App\Http\Middleware;

use App\Enums\AdminUserType;
use App\Models\AdminUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDirectorRole
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var AdminUser $admin */
        $admin = $request->user();

        abort_unless($admin instanceof AdminUser && $admin->type === AdminUserType::Director, 403, 'Solo el director puede realizar esta acción.');

        return $next($request);
    }
}
