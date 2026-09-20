<?php
declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefix = 'TestLab\\';
    if (str_starts_with($class, $prefix)) {
        $relative = substr($class, strlen($prefix));
        if (preg_match('/^[A-Za-z][A-Za-z0-9]*$/D', $relative)) {
            $file = __DIR__ . '/src/' . $relative . '.php';
            if (is_file($file)) { require $file; }
        }
    }
});

$config = require __DIR__ . '/config.example.php';
if (is_file(__DIR__ . '/config.local.php')) {
    $local = require __DIR__ . '/config.local.php';
    if (!is_array($local)) { throw new RuntimeException('config.local.php must return an array.'); }
    $config = array_replace_recursive($config, $local);
}
// Environment variables are convenient for Docker/CI; local PHP config works on shared hosting.
foreach (['DB_HOST'=>'host','DB_PORT'=>'port','DB_NAME'=>'database','DB_USER'=>'username','DB_PASSWORD'=>'password'] as $env=>$key) {
    if (getenv($env) !== false) { $config['db'][$key] = getenv($env); }
}
foreach (['APP_ENV'=>'app_env','TEST_API_KEY'=>'test_api_key','FAULT_MODE'=>'fault_mode','API_DELAY_MS'=>'api_delay_ms'] as $env=>$key) {
    if (getenv($env) !== false) { $config[$key] = getenv($env); }
}
if (getenv('ENABLE_TEST_API') !== false) {
    $config['enable_test_api'] = filter_var(getenv('ENABLE_TEST_API'), FILTER_VALIDATE_BOOL);
}
if (getenv('SECURE_COOKIES') !== false) {
    $config['secure_cookies'] = filter_var(getenv('SECURE_COOKIES'), FILTER_VALIDATE_BOOL);
}
if (!in_array($config['app_env'], ['local','testing','production'], true)) {
    throw new RuntimeException('Invalid APP_ENV.');
}
$modes = ['none','shipping_boundary','discount_rounding','stock_not_decremented','order_total_response'];
if (!in_array($config['fault_mode'], $modes, true)) { throw new RuntimeException('Invalid FAULT_MODE.'); }
if ($config['app_env'] !== 'testing' && ($config['enable_test_api'] || $config['fault_mode'] !== 'none' || (int)$config['api_delay_ms'] !== 0)) {
    throw new RuntimeException('Test API, delays and fault modes require APP_ENV=testing.');
}
if ($config['enable_test_api'] && strlen((string)$config['test_api_key']) < 32) {
    throw new RuntimeException('TEST_API_KEY must contain at least 32 characters.');
}
date_default_timezone_set('UTC');
return $config;
