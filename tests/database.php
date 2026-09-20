<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
try {
    if(!isset($argv[1]) || !preg_match('/^[a-f0-9-]{36}$/D',$argv[1])) {
        throw new RuntimeException('Usage: php tests/database.php <order-uuid> [expected-total-cents=8563] [expected-status=created]');
    }
    $config=require dirname(__DIR__).'/app/bootstrap.php';
    $db=TestLab\Database::connect($config);
    $expected=isset($argv[2])?filter_var($argv[2],FILTER_VALIDATE_INT):8563;
    if($expected===false || $expected<0) { throw new RuntimeException('Expected total must be a nonnegative integer.'); }
    $status=$argv[3]??'created';
    $st=$db->prepare('SELECT o.*,u.workspace_id AS user_workspace FROM orders o JOIN users u ON u.id=o.user_id WHERE o.id=?'); $st->execute([$argv[1]]); $o=$st->fetch();
    if(!$o) { throw new RuntimeException('Order not found.'); }
    $st=$db->prepare('SELECT * FROM order_items WHERE order_id=?'); $st->execute([$argv[1]]); $items=$st->fetchAll();
    $checks=[
        'Expected total (independent test oracle)'=>(int)$o['total_cents']===$expected,
        'Expected status'=>$o['status']===$status,
        'At least one order line'=>count($items)>0,
        'Subtotal matches stored lines'=>(int)$o['subtotal_cents']===array_sum(array_column($items,'line_total_cents')),
        'Total equation'=>(int)$o['total_cents']===(int)$o['subtotal_cents']-(int)$o['discount_cents']+(int)$o['shipping_cents'],
        'Workspace ownership'=>(int)$o['workspace_id']===(int)$o['user_workspace'],
    ];
    foreach($items as $line) { $checks['Line calculation '.$line['sku']]=(int)$line['line_total_cents']===(int)$line['unit_price_cents']*(int)$line['quantity']; }
    $failed=0;
    foreach($checks as $name=>$pass) { echo ($pass?'[PASS] ':'[FAIL] ').$name."\n"; if(!$pass)$failed++; }
    exit($failed?1:0);
} catch(Throwable $e) { fwrite(STDERR,$e->getMessage()."\n"); exit(1); }
