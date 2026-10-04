<?php

return [
    'partner_id' => env('SHOPEE_PARTNER_ID'),
    'partner_key' => env('SHOPEE_PARTNER_KEY'),
    // Live/Test Push Partner Key from Shopee Push settings (push signature verification).
    'push_partner_key' => env('SHOPEE_PUSH_PARTNER_KEY'),
    'redirect_url' => env('SHOPEE_REDIRECT_URL', 'http://localhost:8000/api/shopee/callback'),
    // Exact public URL registered in Shopee Push Console (use behind ngrok / reverse proxies).
    'webhook_url' => env('SHOPEE_WEBHOOK_URL'),
    'sandbox' => env('SHOPEE_SANDBOX', true),
];
