<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;

class StoreInfoController extends Controller
{
    /**
     * Public endpoint for Flutter app preflight and connectivity check.
     * Returns the store/workspace information without exposing sensitive credentials.
     */
    public function show(): JsonResponse
    {
        $primaryWorkspace = Workspace::query()->orderBy('id')->first();

        return response()->json([
            'app' => 'mystore',
            'version' => config('app.version', '1.0.0'),
            'name' => $primaryWorkspace?->name ?? config('app.name', 'MyStore'),
            'status' => 'ready',
            'url' => config('app.url'),
        ]);
    }
}
