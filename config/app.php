<?php

use Illuminate\Support\Facades\Facade;
use Illuminate\Support\ServiceProvider;

return [

    'name' => env('APP_NAME', 'Laravel'),

    'env' => env('APP_ENV', 'production'),

    'debug' => (bool) env('APP_DEBUG', false),

    'url' => env('APP_URL', 'http://localhost'),

    'asset_url' => env('ASSET_URL', null),

    'timezone' => 'Asia/Riyadh',

    'locale' => 'en',

    'fallback_locale' => 'en',

    'faker_locale' => 'en_US',

    'key' => env('APP_KEY'),

    'cipher' => 'AES-256-CBC',

    'maintenance' => [
        'driver' => 'file',
        // 'store' => 'database',
    ],

    'providers' => ServiceProvider::defaultProviders()->merge([
        /*
         * Package Service Providers...
         */

        /*
         * Application Service Providers...
         */
        App\Providers\AppServiceProvider::class,
    ])->toArray(),

    'aliases' => Facade::defaultAliases()->merge([
        // 'Example' => App\Facades\Example::class,
    ])->toArray(),

    // ZATCA Proxy Configuration
    'require_api_key' => env('REQUIRE_API_KEY', true),
    'api_secret_key' => env('API_SECRET_KEY'),
    'allowed_client_ips' => env('ALLOWED_CLIENT_IPS', ''),
    'api_rate_limit' => env('API_RATE_LIMIT', 1000),
    'api_rate_limit_window' => env('API_RATE_LIMIT_WINDOW', 60),
    
    // Logging Configuration
    'enable_request_logging' => env('ENABLE_REQUEST_LOGGING', true),
    'enable_response_logging' => env('ENABLE_RESPONSE_LOGGING', true),
    'log_zatca_requests' => env('LOG_ZATCA_REQUESTS', true),
    'log_zatca_responses' => env('LOG_ZATCA_RESPONSES', true),
    
    // Performance Configuration
    'cache_zatca_responses' => env('CACHE_ZATCA_RESPONSES', true),
    'cache_ttl' => env('CACHE_TTL', 3600),
    'enable_compression' => env('ENABLE_COMPRESSION', true),

];
