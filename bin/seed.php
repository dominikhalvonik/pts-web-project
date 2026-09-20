<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
try {
    $config=require dirname(__DIR__).'/app/bootstrap.php'; $db=TestLab\Database::connect($config);
    if($db->query("SELECT id FROM workspaces WHERE code='demo'")->fetchColumn()!==false) {
        throw new RuntimeException('Demo workspace already exists. No data was modified.');
    }
    $password=getenv('DEMO_PASSWORD')?:'Student123!';
    TestLab\Validation::password(['password'=>$password]);
    if($config['app_env']==='production' && $password==='Student123!') {
        throw new RuntimeException('Set a unique DEMO_PASSWORD when APP_ENV=production.');
    }
    $fixtures=new TestLab\Fixtures($db);
    $fixtures->create('demo','Študent Demo','student@example.test',$password);
    $wid=(new TestLab\Catalog($db))->demoWorkspace();
    $fixtures->addUser($wid,'Druhý Študent','student2@example.test',$password);
    echo "Demo workspace created with 8 products.\nAccounts: student@example.test, student2@example.test\n";
    echo $password==='Student123!'?"Local demo password: Student123!\n":"Password: value of DEMO_PASSWORD (not printed).\n";
} catch(Throwable $e) { fwrite(STDERR,"Seeding failed: ".$e->getMessage()."\n"); exit(1); }
