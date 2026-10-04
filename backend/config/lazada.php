<?php

return [
    'app_key' => env('LAZADA_APP_KEY'),
    'app_secret' => env('LAZADA_APP_SECRET'),
    'redirect_url' => env('LAZADA_REDIRECT_URL', 'https://mystores.melur.com/api/lazada/callback'),
    'sandbox' => env('LAZADA_SANDBOX', false),
];
