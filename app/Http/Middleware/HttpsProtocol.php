<?php

namespace App\Http\Middleware;

use Closure;

class HttpsProtocol {
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if (env('FORCE_HTTPS') == "On" && !$request->secure() && !in_array($request->getHost(), ['localhost', '127.0.0.1'])) {
            return redirect()->secure($request->getRequestUri());
        }
        return $next($request);
    }
}
