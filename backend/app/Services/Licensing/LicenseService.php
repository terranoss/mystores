<?php

namespace App\Services\Licensing;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class LicenseService
{
    public const PRODUCT_CODE = 'mystores';
    public const API_BASE = 'https://stacklane.melur.com/api';

    public const PUBLIC_KEY = <<<EOD
-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAuKfOq3N0Rg47GP8T9O0A
cDMsy58YPF7voxaPhzpKAv5PbhUYHkT7/wLe6F6V6coRD9RbEj5q8+4F2Ck+cEkU
Jvppwz+c8vameLY5TY4RHvtOSX0O8HH13PZKsXMTeTYCdSmiX+mt5juNsWcxdcid
DcAS7K8o7odRReQxCf3Ge5bDBi+7PoAYFU+Np+LlKN+TfrJ0a2NnDo2DJtVXeCfw
x6uWcFTtKDtAp6ErdekilcI9F6Gz8uyL/aW/modfQhqyOjel1+B2IP49zkydySlr
Nj3u3cwy9PvsxGUVYaRFcaq7ww8S5NDmcpi8iC4BoQp40i38eNAQb4mStj3LTSqZ
7QIDAQAB
-----END PUBLIC KEY-----
EOD;

    /**
     * Compute a stable 64-character SHA-256 fingerprint from the host domain and server path.
     */
    public function computeFingerprint(?string $domain = null): string
    {
        $resolvedDomain = strtolower(trim((string) ($domain ?: parse_url(config('app.url'), PHP_URL_HOST) ?: request()->getHost())));
        $serverSeed = php_uname('n') . '|' . dirname(base_path());
        return hash('sha256', $resolvedDomain . '|' . $serverSeed);
    }

    /**
     * Activate license with Stacklane.
     */
    public function activate(string $licenseKey, ?string $domain = null): array
    {
        $licenseKey = strtoupper(trim($licenseKey));
        $domain = $domain ? trim($domain) : (parse_url(config('app.url'), PHP_URL_HOST) ?: request()->getHost());
        $fingerprint = $this->computeFingerprint($domain);

        try {
            $response = Http::timeout(15)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'User-Agent' => 'MyStore-Installer/1.0',
                ])
                ->post(self::API_BASE . '/license/activate', [
                    'key' => $licenseKey,
                    'fingerprint' => $fingerprint,
                    'machine_label' => $domain,
                    'product' => self::PRODUCT_CODE,
                ]);

            if ($response->status() === 422) {
                return [
                    'success' => false,
                    'message' => 'Invalid or revoked license key. Please check your key from Stacklane.',
                ];
            }

            if ($response->status() === 409) {
                return [
                    'success' => false,
                    'message' => 'Activation limit reached. Please deactivate an existing domain in your Stacklane portal.',
                ];
            }

            if (!$response->successful()) {
                return [
                    'success' => false,
                    'message' => 'License verification server returned error: ' . $response->status(),
                ];
            }

            $data = $response->json();
            $token = $data['token'] ?? null;

            if (!$token) {
                return [
                    'success' => false,
                    'message' => 'Malformed response from license server: token missing.',
                ];
            }

            $payload = $this->verifyJwtToken($token);
            if (!$payload) {
                return [
                    'success' => false,
                    'message' => 'Cryptographic signature verification failed. Invalid token signature.',
                ];
            }

            if (($payload['prd'] ?? '') !== self::PRODUCT_CODE) {
                return [
                    'success' => false,
                    'message' => 'License token belongs to a different product (' . ($payload['prd'] ?? 'unknown') . ').',
                ];
            }

            if (strtoupper($payload['sub'] ?? '') !== $licenseKey) {
                return [
                    'success' => false,
                    'message' => 'License token key mismatch.',
                ];
            }

            // Save certificate locally
            $this->saveLicenseCache([
                'key' => $licenseKey,
                'domain' => $domain,
                'fingerprint' => $fingerprint,
                'token' => $token,
                'payload' => $payload,
                'activated_at' => now()->toIso8601String(),
            ]);

            return [
                'success' => true,
                'message' => 'License activated successfully for ' . $domain,
                'license' => [
                    'key' => $licenseKey,
                    'domain' => $domain,
                    'fingerprint' => $fingerprint,
                    'product' => self::PRODUCT_CODE,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('License activation error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Network error: Unable to reach Stacklane licensing server (' . $e->getMessage() . ').',
            ];
        }
    }

    /**
     * Verify RS256 signature of the JWT token using embedded public key.
     */
    public function verifyJwtToken(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$headerB64, $payloadB64, $sigB64] = $parts;
        $data = $headerB64 . '.' . $payloadB64;

        $sig = self::base64UrlDecode($sigB64);
        if (!$sig) {
            return null;
        }

        $verified = openssl_verify($data, $sig, self::PUBLIC_KEY, OPENSSL_ALGO_SHA256);
        if ($verified !== 1) {
            return null;
        }

        $payloadJson = self::base64UrlDecode($payloadB64);
        $payload = json_decode($payloadJson, true);

        return is_array($payload) ? $payload : null;
    }

    /**
     * Save license certificate into storage.
     */
    public function saveLicenseCache(array $data): void
    {
        $path = storage_path('app/license.json');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($data, JSON_PRETTY_PRINT));
    }

    /**
     * Load saved license certificate.
     */
    public function getSavedLicense(): ?array
    {
        $path = storage_path('app/license.json');
        if (!File::exists($path)) {
            return null;
        }

        try {
            $data = json_decode(File::get($path), true);
            return is_array($data) ? $data : null;
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Check if MyStore is already installed.
     */
    public function isInstalled(): bool
    {
        return File::exists(storage_path('installed'));
    }

    /**
     * Mark MyStore as installed.
     */
    public function markInstalled(): void
    {
        File::put(storage_path('installed'), json_encode([
            'installed_at' => now()->toIso8601String(),
            'version' => config('app.version', '1.0.0'),
        ], JSON_PRETTY_PRINT));
    }

    private static function base64UrlDecode(string $input): string|false
    {
        $remainder = strlen($input) % 4;
        if ($remainder) {
            $padlen = 4 - $remainder;
            $input .= str_repeat('=', $padlen);
        }
        return base64_decode(strtr($input, '-_', '+/'));
    }
}
