<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTeamIsActive
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->team && ! $user->team->is_active) {
            return response()->json([
                'message' => 'Your team has been deactivated.',
            ], 403);
        }

        return $next($request);
    }
}
