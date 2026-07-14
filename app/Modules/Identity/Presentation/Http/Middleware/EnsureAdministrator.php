<?php

namespace App\Modules\Identity\Presentation\Http\Middleware;

use App\Modules\Identity\Domain\Authorization\RoleName;
use App\Modules\Identity\Domain\Contracts\AuthorizableUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureAdministrator
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user instanceof AuthorizableUser && $user->hasRole(RoleName::Administrator->value), 403, 'Administrator access is required.');

        return $next($request);
    }
}
