<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class SecurityFirewall
{
    /**
     * Resolve the real client IP address, handling Cloudflare and reverse proxies.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string
     */
    public static function getClientIp($request)
    {
        // 1. Cloudflare header
        if ($cfIp = $request->header('CF-Connecting-IP')) {
            return trim($cfIp);
        }

        // 2. True-Client-IP (Cloudflare Enterprise, Akamai)
        if ($trueIp = $request->header('True-Client-IP')) {
            return trim($trueIp);
        }

        // 3. X-Real-IP (Nginx reverse proxy)
        if ($realIp = $request->header('X-Real-IP')) {
            return trim($realIp);
        }

        // 4. X-Forwarded-For (First IP is the origin client)
        if ($forwarded = $request->header('X-Forwarded-For')) {
            $parts = explode(',', $forwarded);
            $client = trim($parts[0]);
            if (filter_var($client, FILTER_VALIDATE_IP)) {
                return $client;
            }
        }

        // 5. Standard Laravel IP resolution
        return $request->ip() ?: '0.0.0.0';
    }

    /**
     * Check if the client IP is in the authorized administrative IP whitelist.
     *
     * @param  \Illuminate\Http\Request|string  $requestOrIp
     * @return bool
     */
    public static function isIpAllowed($requestOrIp)
    {
        $clientIp = is_string($requestOrIp) ? $requestOrIp : self::getClientIp($requestOrIp);

        $allowedConfig = config('app.allowed_admin_ips', env('ALLOWED_ADMIN_IPS', '104.28.240,127.0.0.1,::1'));
        if (empty($allowedConfig)) {
            return true;
        }

        $allowedList = array_filter(array_map('trim', explode(',', $allowedConfig)));

        foreach ($allowedList as $allowed) {
            if ($allowed === '*' || $allowed === '') {
                return true;
            }

            // Exact IP match
            if ($clientIp === $allowed) {
                return true;
            }

            // Subnet/prefix match (e.g. "104.28.240" matches any IP like "104.28.240.x")
            $cleanPrefix = rtrim($allowed, '.');
            if (str_starts_with($clientIp, $cleanPrefix . '.') || $clientIp === $cleanPrefix) {
                return true;
            }

            if (str_starts_with($clientIp, $allowed)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Intercept and filter incoming HTTP requests for file upload and administrative safety.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $clientIp = self::getClientIp($request);
        $isWhitelistedIp = self::isIpAllowed($clientIp);

        // Check if the request contains file uploads
        $hasUploadedFiles = count($request->allFiles()) > 0
            || $request->hasFile('aiz_file')
            || $request->hasFile('file')
            || $request->hasFile('bulk_file')
            || $request->hasFile('photos')
            || $request->hasFile('id_card')
            || $request->hasFile('customer_photo');

        // Check if the request is calling file upload endpoints
        $isUploadEndpoint = $request->is('aiz-uploader*')
            || $request->is('*/upload*')
            || $request->is('api/v2/*upload*')
            || $request->is('api/v2/file/*');

        // Allow downloading attachments if it's a GET request
        $isAttachmentDownload = $request->is('aiz-uploader/download/*') && $request->isMethod('GET');

        if (($hasUploadedFiles || $isUploadEndpoint) && !$isAttachmentDownload) {
            // Only authenticated admin/staff users coming from an authorized IP are allowed to upload files
            $isAuthorizedAdmin = Auth::check()
                && in_array(Auth::user()->user_type, ['admin', 'staff'])
                && $isWhitelistedIp;

            if (!$isAuthorizedAdmin) {
                Log::warning('Blocked unauthorized file upload attempt', [
                    'client_ip' => $clientIp,
                    'url' => $request->fullUrl(),
                    'user_id' => Auth::id() ?: null,
                    'user_type' => Auth::check() ? Auth::user()->user_type : 'guest',
                    'files' => array_keys($request->allFiles()),
                ]);

                if ($request->expectsJson() || $request->is('api/*') || $request->ajax()) {
                    return response()->json([
                        'result' => false,
                        'status' => false,
                        'message' => 'File uploads are restricted to authorized administrators only.'
                    ], 403);
                }

                if (function_exists('flash')) {
                    flash(translate('File uploads are restricted to authorized administrators only.'))->error();
                }

                return redirect()->back();
            }
        }

        return $next($request);
    }
}
