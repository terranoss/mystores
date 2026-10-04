<?php

use Illuminate\Support\Facades\Route;

// Serve the React SPA for all non-API routes
Route::get('/{any?}', function () {
    $paths = [
        public_path('index.html'),
        public_path('dist/index.html'),
    ];
    foreach ($paths as $path) {
        if (file_exists($path)) {
            return response()->file($path, [
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ]);
        }
    }
    return response()->json(['message' => 'Frontend not built yet. Run: cd frontend && npm run build'], 200);
})->where('any', '^(?!api($|/)).*$');
