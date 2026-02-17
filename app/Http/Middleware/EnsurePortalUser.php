<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePortalUser
{
    /**
     * Ensure the user is authenticated, has the Client role, and has a client_id (portal access).
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            return redirect()->route('login');
        }

        $user = $request->user();

        if (! $user->hasRole('Client')) {
            return redirect()->intended('/dashboard');
        }

        if (empty($user->client_id)) {
            abort(403, 'Portal access is not configured for your account.');
        }

        return $next($request);
    }
}
