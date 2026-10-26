<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * The security headers added to every response.
     *
     * @var array<string, string>
     */
    protected array $headers = [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'DENY',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Permissions-Policy' => 'camera=(), microphone=(), payment=(), usb=(), geolocation=(self)',
        'Cross-Origin-Opener-Policy' => 'same-origin',
    ];

    /**
     * The HSTS policy, only sent over HTTPS so local HTTP development keeps working.
     */
    protected string $strictTransportSecurity = 'max-age=31536000; includeSubDomains';

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        foreach ($this->headers as $name => $value) {
            $response->headers->set($name, $value, replace: false);
        }

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', $this->strictTransportSecurity, replace: false);
        }

        return $response;
    }
}
