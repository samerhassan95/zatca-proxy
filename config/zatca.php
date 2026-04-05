<?php

return [
    /*
    |--------------------------------------------------------------------------
    | ZATCA API Endpoints
    |--------------------------------------------------------------------------
    |
    | Official ZATCA API endpoints for different environments
    |
    */
    'endpoints' => [
        'developer' => env('ZATCA_DEVELOPER_ENDPOINT', 'https://gw-apic-gov.gazt.gov.sa/e-invoicing/developer-portal'),
        'simulation' => env('ZATCA_SIMULATION_ENDPOINT', 'https://gw-apic-gov.gazt.gov.sa/e-invoicing/simulation'),
        'production' => env('ZATCA_PRODUCTION_ENDPOINT', 'https://gw-apic-gov.gazt.gov.sa/e-invoicing/core'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Environment
    |--------------------------------------------------------------------------
    |
    | Default ZATCA environment to use when not specified in requests
    |
    */
    'default_environment' => env('ZATCA_ENVIRONMENT', 'simulation'),

    /*
    |--------------------------------------------------------------------------
    | API Security Configuration
    |--------------------------------------------------------------------------
    |
    | Security settings for the proxy API
    |
    */
    'security' => [
        'require_api_key' => env('REQUIRE_API_KEY', true),
        'api_secret_key' => env('API_SECRET_KEY'),
        'allowed_client_ips' => env('ALLOWED_CLIENT_IPS', ''),
        'require_client_certificate' => env('REQUIRE_CLIENT_CERTIFICATE', false),
        'rate_limit' => env('API_RATE_LIMIT', 1000),
        'rate_limit_window' => env('API_RATE_LIMIT_WINDOW', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Configure what gets logged
    |
    */
    'logging' => [
        'enable_request_logging' => env('ENABLE_REQUEST_LOGGING', true),
        'enable_response_logging' => env('ENABLE_RESPONSE_LOGGING', true),
        'log_zatca_requests' => env('LOG_ZATCA_REQUESTS', true),
        'log_zatca_responses' => env('LOG_ZATCA_RESPONSES', true),
        'log_sensitive_data' => env('LOG_SENSITIVE_DATA', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Configuration
    |--------------------------------------------------------------------------
    |
    | Performance and caching settings
    |
    */
    'performance' => [
        'cache_zatca_responses' => env('CACHE_ZATCA_RESPONSES', true),
        'cache_ttl' => env('CACHE_TTL', 3600),
        'enable_compression' => env('ENABLE_COMPRESSION', true),
        'request_timeout' => env('ZATCA_REQUEST_TIMEOUT', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook Configuration
    |--------------------------------------------------------------------------
    |
    | Optional webhook notifications for completed requests
    |
    */
    'webhook' => [
        'enabled' => env('WEBHOOK_ENABLED', false),
        'url' => env('WEBHOOK_URL'),
        'secret' => env('WEBHOOK_SECRET'),
        'timeout' => env('WEBHOOK_TIMEOUT', 10),
        'retry_attempts' => env('WEBHOOK_RETRY_ATTEMPTS', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Monitoring Configuration
    |--------------------------------------------------------------------------
    |
    | Monitoring and alerting settings
    |
    */
    'monitoring' => [
        'enable_metrics' => env('ENABLE_METRICS', true),
        'metrics_retention_days' => env('METRICS_RETENTION_DAYS', 30),
        'alert_on_failures' => env('ALERT_ON_FAILURES', true),
        'failure_threshold' => env('FAILURE_THRESHOLD', 10),
    ],
];