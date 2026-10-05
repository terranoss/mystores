<?php

namespace App\Services;

use App\Models\WorkspaceCredential;
use Illuminate\Support\Facades\Cache;

class WorkspaceConfig
{
    /**
     * Get Shopee configuration for a workspace
     */
    public function getShopeeConfig(int $workspaceId): array
    {
        return $this->getCredential($workspaceId, 'shopee');
    }

    /**
     * Get Lazada configuration for a workspace
     */
    public function getLazadaConfig(int $workspaceId): array
    {
        return $this->getCredential($workspaceId, 'lazada');
    }

    /**
     * Get credential for a workspace and platform
     */
    private function getCredential(int $workspaceId, string $platform): array
    {
        // Try to get from cache first
        $cacheKey = "workspace_config_{$workspaceId}_{$platform}";
        $cached = Cache::get($cacheKey);
        
        if ($cached !== null) {
            return $cached;
        }

        // Get from database
        $credential = WorkspaceCredential::where('workspace_id', $workspaceId)
            ->where('platform', $platform)
            ->where('is_active', true)
            ->first();

        // Format the response to match config file structure with fallback to global config
        $result = match ($platform) {
            'shopee' => [
                'partner_id' => $credential?->partner_id ?: config('shopee.partner_id'),
                'partner_key' => $credential?->partner_key ?: config('shopee.partner_key'),
                'push_partner_key' => $credential?->push_partner_key ?: config('shopee.push_partner_key'),
                'redirect_url' => $credential?->redirect_url ?: config('shopee.redirect_url'),
                'webhook_url' => $credential?->redirect_url
                    ? rtrim($credential->redirect_url, '/') . '/api/shopee/webhook'
                    : config('shopee.webhook_url'),
                'sandbox' => config('shopee.sandbox', false),
            ],
            'lazada' => [
                'app_key' => $credential?->app_key ?: config('lazada.app_key'),
                'app_secret' => $credential?->app_secret ?: config('lazada.app_secret'),
                'redirect_url' => $credential?->redirect_url ?: config('lazada.redirect_url', 'https://mystores.melur.com/api/lazada/callback'),
                'sandbox' => config('lazada.sandbox', false),
            ],
            default => [],
        };

        // Cache for 1 hour
        Cache::put($cacheKey, $result, 3600);

        return $result;
    }

    /**
     * Clear cached config for a workspace
     */
    public function clearCache(int $workspaceId): void
    {
        Cache::forget("workspace_config_{$workspaceId}_shopee");
        Cache::forget("workspace_config_{$workspaceId}_lazada");
    }
}