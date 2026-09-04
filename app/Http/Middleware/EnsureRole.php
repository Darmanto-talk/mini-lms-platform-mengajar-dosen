<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $user = $request->user();

        if (! $user || $user->role !== $role) {
            abort(403, $role === 'teacher'
                ? 'Halaman ini khusus dosen.'
                : 'Halaman ini khusus mahasiswa.');
        }

        return $next($request);
    }
}
