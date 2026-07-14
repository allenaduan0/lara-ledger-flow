<?php

namespace App\Modules\Transaction\Presentation\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequireDemoMode
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(config('demo.enabled'), 403, 'Simulated deposits are available only in demo mode.');

        return $next($request);
    }
}
