<?php
declare(strict_types=1);

use TestLab\{ApiError,Auth,Catalog,Database,Fixtures,Http,Orders,Pricing,Validation};

ini_set('display_errors','0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
$requestId=bin2hex(random_bytes(8)); header('X-Request-Id: '.$requestId);
// Do not allow cross-origin credentialed API requests. No permissive CORS headers.
try {
    $config=require dirname(__DIR__,2).'/app/bootstrap.php';
    $route=$_GET['route']??'/health';
    if(!is_string($route)||strlen($route)>200) { throw new ApiError(400,'INVALID_ROUTE','Neplatná cesta.'); }
    $route='/'.trim($route,'/'); $method=$_SERVER['REQUEST_METHOD']??'GET';
    $db=Database::connect($config); $auth=new Auth($db,$config); $catalog=new Catalog($db);
    $orders=new Orders($db,$catalog,$config['fault_mode']);
    if($config['app_env']==='testing' && (int)$config['api_delay_ms']>0 && $route!=='/health') {
        usleep(min(3000,(int)$config['api_delay_ms'])*1000);
    }
    if($route==='/health' && $method==='GET') {
        $db->query('SELECT 1'); Http::send(['status'=>'ok','application'=>'TestLab Shop','version'=>'1.0.0']);
    }
    if($route==='/auth/me' && $method==='GET') { Http::send(['user'=>$auth->current(),'csrf_token'=>$auth->csrf()]); }
    if($route==='/auth/login' && $method==='POST') { Http::send($auth->login(Http::body())); }
    if($route==='/auth/token' && $method==='POST') { Http::send($auth->token(Http::body()),201); }
    if($route==='/auth/logout' && $method==='POST') { Http::body(); Http::send($auth->logout()); }
    if($route==='/products' && $method==='GET') {
        $user=$auth->current(); $wid=$user['workspace_id']??$catalog->demoWorkspace();
        Http::send(['products'=>$catalog->list($wid,$_GET)]);
    }
    if($route==='/cart/quote' && $method==='POST') {
        $user=$auth->current(); $wid=$user['workspace_id']??$catalog->demoWorkspace(); $cart=Validation::cart(Http::body());
        Http::send(Pricing::quote($cart,$catalog->forCart($wid,$cart),$config['fault_mode']));
    }
    if($route==='/orders' && $method==='GET') { Http::send($orders->list($auth->current(true),$_GET)); }
    if($route==='/orders' && $method==='POST') {
        $user=$auth->current(true); $auth->checkCsrf(); $result=$orders->create($user,Http::body(),Http::header('Idempotency-Key'));
        Http::send($result['order'],$result['replayed']?200:201,['replayed'=>$result['replayed']]);
    }
    if(preg_match('#^/orders/([a-f0-9-]{36})$#D',$route,$m) && $method==='GET') { Http::send($orders->get($auth->current(true),$m[1])); }
    if(preg_match('#^/orders/([a-f0-9-]{36})/cancel$#D',$route,$m) && $method==='POST') {
        $user=$auth->current(true); $auth->checkCsrf(); Http::body(); Http::send($orders->cancel($user,$m[1]));
    }
    if(str_starts_with($route,'/testing/')) {
        // A disabled test API looks nonexistent. Never expose the key to Vue or a normal student browser.
        if(!$config['enable_test_api']||$config['app_env']!=='testing') { throw new ApiError(404,'NOT_FOUND','Endpoint neexistuje.'); }
        if(!hash_equals($config['test_api_key'],Http::header('X-Test-Key'))) { throw new ApiError(403,'TEST_API_FORBIDDEN','Neplatný testovací kľúč.'); }
        $fixtures=new Fixtures($db);
        if($route==='/testing/fixtures' && $method==='POST') { Http::body(); Http::send($fixtures->createRun(),201); }
        if(preg_match('#^/testing/fixtures/(run-[a-f0-9]{24})$#D',$route,$m) && $method==='DELETE') { Http::send($fixtures->deleteRun($m[1])); }
    }
    throw new ApiError(404,'NOT_FOUND','Endpoint alebo HTTP metóda neexistuje.');
} catch(ApiError $e) { Http::fail($e,$requestId); }
catch(Throwable $e) {
    // No stack traces, SQL, passwords or configuration details in HTTP responses.
    error_log('TestLab '.$requestId.' '.get_class($e).': '.$e->getMessage());
    $status=($e instanceof PDOException || str_contains($e->getMessage(),'pdo_mysql'))?503:500;
    http_response_code($status);
    echo json_encode(['error'=>['code'=>$status===503?'DATABASE_UNAVAILABLE':'INTERNAL_ERROR',
        'message'=>$status===503?'Databáza nie je dostupná. Skontrolujte konfiguráciu a inštaláciu.':'Server nedokázal spracovať požiadavku.',
        'details'=>(object)[],'request_id'=>$requestId]],JSON_UNESCAPED_UNICODE);
}
