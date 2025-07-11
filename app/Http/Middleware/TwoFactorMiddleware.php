<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TwoFactorMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return $next($request);
        }

        // Check if tenant requires 2FA
        $securitySettings = $user->tenant?->securitySettings;
        $requires2FA = $securitySettings?->require_2fa ?? false;

        // If 2FA is required but not enabled, redirect to setup
        if ($requires2FA && !$user->two_factor_enabled) {
            if (!$request->routeIs('two-factor.*')) {
                return redirect()->route('two-factor.show')
                    ->with('warning', 'Two-factor authentication is required for your account.');
            }
        }

        return $next($request);
    }
}