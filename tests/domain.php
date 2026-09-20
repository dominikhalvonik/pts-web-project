<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
use TestLab\{ApiError,Fixtures,Pricing,Validation};
$passed=0; $failed=0;
function test(string $name, callable $fn): void {
    global $passed,$failed;
    try { $fn(); echo "[PASS] $name\n"; $passed++; }
    catch(Throwable $e) { echo "[FAIL] $name: ".$e->getMessage()."\n"; $failed++; }
}
function eq(mixed $expected,mixed $actual): void {
    if($expected!==$actual) { throw new RuntimeException('Expected '.var_export($expected,true).'; got '.var_export($actual,true)); }
}
function rejects(callable $fn,string $code): void {
    try { $fn(); } catch(ApiError $e) { eq($code,$e->errorCode); return; }
    throw new RuntimeException('Expected rejection '.$code);
}
$products=[];
foreach(Fixtures::products() as $p) {
    $products[$p[0]]=['sku'=>$p[0],'name'=>$p[1],'price_cents'=>$p[4],'stock'=>$p[5],'active'=>1];
}
$input=['items'=>[['sku'=>'KB-001','quantity'=>2],['sku'=>'MS-001','quantity'=>1]],'coupon_code'=>'STUDENT10','delivery_method'=>'courier'];
$cart=Validation::cart($input);
$q=Pricing::quote($cart,$products);
test('Reference subtotal 89.70 EUR',fn()=>eq(8970,$q['subtotal_cents']));
test('Reference discount 8.97 EUR',fn()=>eq(897,$q['discount_cents']));
test('Reference shipping 4.90 EUR',fn()=>eq(490,$q['shipping_cents']));
test('Reference total 85.63 EUR',fn()=>eq(8563,$q['total_cents']));
test('Line total uses server price',fn()=>eq(6980,$q['items'][0]['line_total_cents']));
test('Integer monetary result',fn()=>eq(true,is_int($q['total_cents'])));
test('No coupon',function()use($input,$products){$input['coupon_code']='';eq(9460,Pricing::quote(Validation::cart($input),$products)['total_cents']);});
test('Pickup is free',function()use($input,$products){$input['delivery_method']='pickup';eq(8073,Pricing::quote(Validation::cart($input),$products)['total_cents']);});
$boundary=['items'=>[['sku'=>'BAG-001','quantity'=>4]],'coupon_code'=>'','delivery_method'=>'courier'];
test('Free shipping at exactly 100 EUR',fn()=>eq(0,Pricing::quote(Validation::cart($boundary),$products)['shipping_cents']));
test('Free shipping threshold after discount',function()use($boundary,$products){$boundary['coupon_code']='STUDENT10';eq(9490,Pricing::quote(Validation::cart($boundary),$products)['total_cents']);});
test('Boundary mutation is observable',fn()=>eq(490,Pricing::quote(Validation::cart($boundary),$products,'shipping_boundary')['shipping_cents']));
$rounding=['items'=>[['sku'=>'PAD-001','quantity'=>1]],'coupon_code'=>'STUDENT10','delivery_method'=>'pickup'];
test('Round discount half up (99.5 -> 100 cents)',fn()=>eq(100,Pricing::quote(Validation::cart($rounding),$products)['discount_cents']));
test('Rounding mutation is observable',fn()=>eq(99,Pricing::quote(Validation::cart($rounding),$products,'discount_rounding')['discount_cents']));
test('Discount rounded once on the complete subtotal',function()use($rounding,$products){$rounding['items'][0]['quantity']=2;eq(199,Pricing::quote(Validation::cart($rounding),$products)['discount_cents']);});
test('Empty cart is rejected',fn()=>rejects(fn()=>Validation::cart(['items'=>[],'delivery_method'=>'courier']),'VALIDATION_ERROR'));
foreach([0,-1,21,1.5,'2',true,null] as $bad) {
    test('Invalid quantity '.json_encode($bad),function()use($input,$bad){$input['items'][0]['quantity']=$bad;rejects(fn()=>Validation::cart($input),'VALIDATION_ERROR');});
}
test('Quantity 20 is valid',function()use($input){$input['items'][0]['quantity']=20;eq(20,Validation::cart($input)['items'][0]['quantity']);});
test('Duplicate SKU is rejected',function()use($input){$input['items'][]=$input['items'][0];rejects(fn()=>Validation::cart($input),'VALIDATION_ERROR');});
test('Unknown coupon is rejected',function()use($input){$input['coupon_code']='FREE100';rejects(fn()=>Validation::cart($input),'INVALID_COUPON');});
test('Coupon normalized to uppercase',function()use($input){$input['coupon_code']=' student10 ';eq('STUDENT10',Validation::cart($input)['coupon_code']);});
test('Unknown delivery is rejected',function()use($input){$input['delivery_method']='teleport';rejects(fn()=>Validation::cart($input),'VALIDATION_ERROR');});
test('Unknown product is rejected',function()use($input,$products){$input['items'][0]['sku']='BAD-001';rejects(fn()=>Pricing::quote(Validation::cart($input),$products),'PRODUCT_NOT_FOUND');});
test('Out-of-stock product is rejected',function()use($input,$products){$input['items']=[['sku'=>'HUB-001','quantity'=>1]];rejects(fn()=>Pricing::quote(Validation::cart($input),$products),'OUT_OF_STOCK');});
test('Insufficient stock is rejected',function()use($input,$products){$products['KB-001']['stock']=1;rejects(fn()=>Pricing::quote(Validation::cart($input),$products),'OUT_OF_STOCK');});
test('Inactive product is rejected',function()use($input,$products){$products['KB-001']['active']=0;rejects(fn()=>Pricing::quote(Validation::cart($input),$products),'PRODUCT_NOT_FOUND');});
test('Client unit price is ignored',function()use($input,$products){$input['items'][0]['price_cents']=1;eq(8563,Pricing::quote(Validation::cart($input),$products)['total_cents']);});
test('SKU order normalized',function()use($input){$input['items']=array_reverse($input['items']);eq('KB-001',Validation::cart($input)['items'][0]['sku']);});
$order=$input+['customer'=>['name'=>'Testovací Študent','email'=>'student@example.test','address'=>'Testovacia 12','city'=>'Žilina','postal_code'=>'010 01'],'note'=>'E2E test','accept_terms'=>true,'expected_total_cents'=>8563];
test('Valid order and postal normalization',fn()=>eq('01001',Validation::order($order)['customer']['postal_code']));
test('Unicode customer name preserved',fn()=>eq('Testovací Študent',Validation::order($order)['customer']['name']));
test('Courier requires address',function()use($order){$order['customer']['address']='';rejects(fn()=>Validation::order($order),'VALIDATION_ERROR');});
test('Invalid postal code rejected',function()use($order){$order['customer']['postal_code']='ABC';rejects(fn()=>Validation::order($order),'VALIDATION_ERROR');});
test('Invalid email rejected',function()use($order){$order['customer']['email']='not-email';rejects(fn()=>Validation::order($order),'VALIDATION_ERROR');});
test('Terms must be boolean true',function()use($order){$order['accept_terms']='true';rejects(fn()=>Validation::order($order),'VALIDATION_ERROR');});
test('Expected total must be integer',function()use($order){$order['expected_total_cents']='8563';rejects(fn()=>Validation::order($order),'VALIDATION_ERROR');});
test('Pickup discards unnecessary address',function()use($order){$order['delivery_method']='pickup';eq('',Validation::order($order)['customer']['address']);});
test('Oversized note rejected',function()use($order){$order['note']=str_repeat('x',501);rejects(fn()=>Validation::order($order),'VALIDATION_ERROR');});
test('Password length lower bound',fn()=>rejects(fn()=>Validation::password(['password'=>'abc']),'VALIDATION_ERROR'));
test('Password 72-byte limit',fn()=>rejects(fn()=>Validation::password(['password'=>str_repeat('a',73)]),'VALIDATION_ERROR'));
test('Control characters rejected',fn()=>rejects(fn()=>Validation::text(['name'=>"X\0Y"],'name',20),'VALIDATION_ERROR'));
test('Structured value instead of text rejected',fn()=>rejects(fn()=>Validation::text(['name'=>['x']],'name',20),'VALIDATION_ERROR'));
test('UUID version and variant',function(){eq(1,preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/D',TestLab\Http::uuid()));});
echo "\n$passed passed; $failed failed.\n"; exit($failed?1:0);
