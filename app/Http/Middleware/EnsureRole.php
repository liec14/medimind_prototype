<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    /** Usage: ->middleware('role:doctor,admin') */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $allowed = array_map(fn (string $r) => Role::from($r), $roles);

        abort_unless($request->user()?->hasRole(...$allowed), 403);

        return $next($request);
    }
}
