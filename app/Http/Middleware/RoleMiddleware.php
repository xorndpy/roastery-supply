<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$roles
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        // Cek apakah user sudah login
        if (!$request->user()) {
            return redirect()->route('login');
        }

        // Cek apakah user punya salah satu role yang diizinkan
        foreach ($roles as $role) {
            if ($request->user()->hasRole($role)) {
                return $next($request);
            }
        }

        // Kalau gak punya akses, abort 403
        abort(403, 'Anda tidak memiliki akses ke halaman ini.');
    }
}