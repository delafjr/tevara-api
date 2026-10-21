<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ForceJsonResponse
{
    public function handle(Request $request, Closure $next)
    {
        if (! $request->is('api/documentation*') && ! $request->is('docs*')) {
            $request->headers->set('Accept', 'application/json');
        }

        return $next($request);
    }
}
