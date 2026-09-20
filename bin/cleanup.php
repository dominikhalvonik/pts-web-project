<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
try {
    $config=require dirname(__DIR__).'/app/bootstrap.php'; $db=TestLab\Database::connect($config);
    $tokens=$db->exec('DELETE FROM api_tokens WHERE expires_at<=UTC_TIMESTAMP()');
    $limits=$db->exec('DELETE FROM login_limits WHERE window_started<UTC_TIMESTAMP()-INTERVAL 1 DAY');
    echo "Deleted expired tokens: $tokens; old rate-limit buckets: $limits\n";
} catch(Throwable $e) { fwrite(STDERR,$e->getMessage()."\n"); exit(1); }
