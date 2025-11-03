<?php

namespace Sayed\Payment\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class DisableCsrfForWebhooks
{
    public function handle(Request $request, Closure $next)
    {
        return $next($request);
    }
}
