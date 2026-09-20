<?php
// Copy to config.local.php. This directory MUST be outside the public document root.
return [
    'app_env' => 'local', // local | testing | production
    'db' => [
        'host' => '127.0.0.1', 'port' => 3306,
        'database' => 'testlab_shop', 'username' => 'testlab_app',
        'password' => 'CHANGE_ME',
    ],
    'session_name' => 'TESTLABSHOP',
    // null = detect HTTPS directly; true = HTTPS (also behind a trusted TLS proxy).
    'secure_cookies' => null,
    'session_ttl_seconds' => 7200,
    'token_ttl_seconds' => 7200,
    'enable_test_api' => false,
    // Generate with: php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
    'test_api_key' => '',
    // Faults are accepted ONLY in app_env=testing. Never set via a public request.
    'fault_mode' => 'none', // none | shipping_boundary | discount_rounding | stock_not_decremented | order_total_response
    'api_delay_ms' => 0, // 0..3000, testing environment only
];
