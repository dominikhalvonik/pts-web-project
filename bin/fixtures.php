<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
try {
    $config=require dirname(__DIR__).'/app/bootstrap.php';
    if($config['app_env']==='production') { throw new RuntimeException('Fixtures require local/testing environment.'); }
    $fixtures=new TestLab\Fixtures(TestLab\Database::connect($config));
    $action=$argv[1]??'';
    $result=match($action) {
        'create'=>$fixtures->createRun(),
        'delete'=>$fixtures->deleteRun($argv[2]??''),
        default=>throw new RuntimeException('Usage: php bin/fixtures.php create | delete run-<24 hex characters>'),
    };
    echo json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)."\n";
} catch(Throwable $e) { fwrite(STDERR,$e->getMessage()."\n"); exit(1); }
