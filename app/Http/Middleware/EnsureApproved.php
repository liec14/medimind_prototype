<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApproved
{
    /** Doctors and pharmacies wait on an admin; everyone else is auto-approved. */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->isApproved()) {
            return redirect()->route('pending');
        }

        return $next($request);
    }
}
