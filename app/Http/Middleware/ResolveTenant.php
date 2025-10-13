<?php
// app/Http/Middleware/ResolveTenant.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use App\Models\Organization;

class ResolveTenant
{
    public function handle(Request $request, Closure $next)
    {
        $slug = $request->route('org') ?? $request->route('organization');

        if (is_object($slug) && method_exists($slug, '__toString')) {
            $slug = (string) $slug;
        }

        $org = Organization::where('slug', $slug)->first();
        abort_unless($org, 404, 'Organization not found.');

        // bind to container
        App::instance('tenant', $org);

        // normalize {organization} param for routes that expect a model
        if ($request->route('organization') && !($request->route('organization') instanceof Organization)) {
            $request->route()->setParameter('organization', $org);
        }

        return $next($request);
    }
}
