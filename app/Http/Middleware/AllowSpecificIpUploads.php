<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AllowSpecificIpUploads
{
    public function handle(Request $request, Closure $next): Response
    {
        // যদি রিকোয়েস্টে কোনো ফাইল বা ছবি আপলোড করা হয়
        if (!empty($request->allFiles())) {
            
            $clientIp = $request->header('CF-Connecting-IP') 
                        ?? $request->header('X-Forwarded-For') 
                        ?? $request->ip();

            if (str_contains($clientIp, ',')) {
                $clientIp = trim(explode(',', $clientIp)[0]);
            }

            // আপনার ব্রডব্যান্ড নেটওয়ার্কের আইপি প্রিফিক্স (Prefix)
            $allowedPrefix = '103.217.111.';

            // আইপি যদি এই প্রিফিক্স দিয়ে শুরু না হয়, তবেই ব্লক করবে
            if (!str_starts_with($clientIp, $allowedPrefix)) {
                return response("IP Mismatch! Your IP is: " . $clientIp, 403);
            }
        }

        return $next($request);
    }
}