<?php

namespace App\Modules\Identity\Presentation\Http\Middleware;

use App\Modules\Identity\Domain\Contracts\AuthorizableUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequirePermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();
        abort_unless($user instanceof AuthorizableUser && $user->hasPermission($permission), 403);

        return $next($request);
    }
}
