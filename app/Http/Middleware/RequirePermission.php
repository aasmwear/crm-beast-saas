<?php

namespace App\Http\Middleware;

use App\Services\PermissionsService;
use Closure;
use Illuminate\Http\Request;

final class RequirePermission
{
    public function __construct(protected PermissionsService $perms) {}

    public function handle(Request $request, Closure $next, string $entity, string $action): mixed
    {
        $user = $request->user();

        if (! $this->perms->can($user, $entity, $action)) {
            abort(403, 'Forbidden');
        }

        return $next($request);
    }
}
