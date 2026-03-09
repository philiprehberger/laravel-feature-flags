<?php

declare(strict_types=1);

namespace PhilipRehberger\FeatureFlags\Middleware;

use Closure;
use Illuminate\Http\Request;
use PhilipRehberger\FeatureFlags\FeatureManager;
use Symfony\Component\HttpFoundation\Response;

class FeatureFlagMiddleware
{
    public function __construct(protected FeatureManager $manager) {}

    /**
     * Handle an incoming request.
     *
     * Aborts with 403 if the named feature is not active.
     * When a user is authenticated, the per-user check (including rollout) is used.
     * Otherwise the global active state is checked.
     */
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $user = $request->user();

        $active = $user !== null
            ? $this->manager->for($user)->active($feature)
            : $this->manager->active($feature);

        if (! $active) {
            abort(403, "Feature [{$feature}] is not active.");
        }

        return $next($request);
    }
}
