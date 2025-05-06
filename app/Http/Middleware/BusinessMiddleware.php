<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BusinessMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next)
    {
        // Get active business from session
        $businessId = session('active_business_id');

        if (!$businessId) {
            // If no active business, get the first business the user has access to
            $businessId = auth()->user()->businesses()->first()?->id;

            if ($businessId) {
                session(['active_business_id' => $businessId]);
            } else {
                // No business access, redirect to business creation
                return redirect()->route('businesses.create');
            }
        }

        // Add active business to all requests
        $request->merge(['business_id' => $businessId]);

        return $next($request);
    }
}
