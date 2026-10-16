<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\Response;

class CheckInstallation
{
    public function handle(Request $request, Closure $next): Response
    {
        $isInstalled = File::exists(storage_path('installed'));
        $isInstallRoute = $request->is('install') || $request->is('api/install*');
        $isUpRoute = $request->is('up');

        if ($isUpRoute || app()->environment('testing')) {
            return $next($request);
        }

        // If app is ALREADY installed, prevent re-entering installer routes
        if ($isInstalled && $isInstallRoute) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => 'MyStore is already installed.',
                ], 403);
            }

            return redirect('/');
        }

        // If app is NOT installed, redirect everything to /install
        if (!$isInstalled && !$isInstallRoute) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'error' => 'not_installed',
                    'message' => 'MyStore has not been installed yet. Please navigate to /install to set up.',
                ], 503);
            }

            return redirect('/install');
        }

        return $next($request);
    }
}
