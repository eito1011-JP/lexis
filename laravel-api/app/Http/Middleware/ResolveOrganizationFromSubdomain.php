<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ResolveOrganizationFromSubdomain
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $host = $request->getHost();

        // Extract subdomain: e.g., org.example.com => "org"
        $subdomain = $this->extractSubdomain($host);

        if ($subdomain !== null) {
            $organization = DB::table('organizations')->where('slug', $subdomain)->first();
            if ($organization) {
                // Share organization_id in request attributes for later use
                $request->attributes->set('organization_id', $organization->id);
            }
        }

        return $next($request);
    }

    private function extractSubdomain(string $host): ?string
    {
        // Assume base domain has at least two labels; ignore localhost and IPs
        if ($host === 'localhost' || filter_var($host, FILTER_VALIDATE_IP)) {
            return null;
        }

        $parts = explode('.', $host);
        if (count($parts) < 3) {
            return null; // no subdomain
        }

        return $parts[0] ?: null;
    }
}
