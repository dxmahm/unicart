<?php

namespace App\Http\Middleware;

use Closure;
use Auth;

class IsAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if (Auth::check() && (Auth::user()->user_type == 'admin' || Auth::user()->user_type == 'staff')) {
            if (!SecurityFirewall::isIpAllowed($request)) {
                $clientIp = SecurityFirewall::getClientIp($request);
                \Log::warning("Blocked unauthorized admin access attempt from IP: {$clientIp} for user ID: " . Auth::id());
                if ($request->expectsJson() || $request->ajax()) {
                    return response()->json(['error' => 'Access denied: Unauthorized IP address.'], 403);
                }
                abort(403, 'Access denied: Your IP address is not authorized for administrative access.');
            }
            return $next($request);
        }
        else{
            abort(404);
        }
    }
}
