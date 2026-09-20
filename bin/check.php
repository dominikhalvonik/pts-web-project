<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
$ok=true;
foreach(['PHP >= 8.2'=>version_compare(PHP_VERSION,'8.2.0','>='),'64-bit PHP'=>PHP_INT_SIZE===8,
    'PDO'=>extension_loaded('pdo'),'pdo_mysql'=>extension_loaded('pdo_mysql'),'JSON'=>extension_loaded('json'),
    'session'=>extension_loaded('session'),'configuration supplied'=>is_file(dirname(__DIR__).'/app/config.local.php') || (getenv('DB_HOST')!==false && getenv('DB_NAME')!==false && getenv('DB_USER')!==false && getenv('DB_PASSWORD')!==false)] as $name=>$pass) {
    echo ($pass?'[OK] ':'[FAIL] ').$name."\n"; $ok=$ok&&$pass;
}
if($ok) {
    try { $config=require dirname(__DIR__).'/app/bootstrap.php'; $db=TestLab\Database::connect($config);
        echo '[OK] Database connection; server '.$db->getAttribute(PDO::ATTR_SERVER_VERSION)."\n";
        foreach(['workspaces','users','products','orders','order_items','api_tokens','login_limits'] as $table) {
            $db->query("SELECT 1 FROM $table LIMIT 1"); echo "[OK] Table $table\n";
        }
    } catch(Throwable $e) { echo '[FAIL] '.$e->getMessage()."\n"; $ok=false; }
}
exit($ok?0:1);
