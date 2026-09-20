<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
try {
    $config=require dirname(__DIR__).'/app/bootstrap.php';
    $db=TestLab\Database::connect($config);
    if($db->query('SHOW TABLES')->fetchColumn()!==false) {
        throw new RuntimeException('Database must be empty. This installer never drops existing tables.');
    }
    $sql=file_get_contents(dirname(__DIR__).'/database/schema.sql');
    foreach(explode(';',$sql) as $statement) { if(trim($statement)!=='') { $db->exec($statement); } }
    echo "Schema installed. Run: php bin/seed.php\n";
} catch(Throwable $e) { fwrite(STDERR,"Installation failed: ".$e->getMessage()."\n"); exit(1); }
