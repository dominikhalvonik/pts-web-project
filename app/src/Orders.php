<?php
declare(strict_types=1);
namespace TestLab;
use PDO;

final class Orders
{
    public function __construct(private readonly PDO $db,private readonly Catalog $catalog,private readonly string $fault) {}
    public function create(array $user,array $input,string $key): array
    {
        if(!preg_match('/^[A-Za-z0-9._:-]{8,128}$/D',$key)) { throw new ApiError(422,'IDEMPOTENCY_KEY_REQUIRED','Chýba platná hlavička Idempotency-Key (8 až 128 znakov).'); }
        $data=Validation::order($input);
        $hash=hash('sha256',json_encode($data,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
        $this->db->beginTransaction();
        try {
            // Serialize creation by account: concurrent retries with the same key cannot insert twice.
            $st=$this->db->prepare('SELECT id FROM users WHERE id=? FOR UPDATE'); $st->execute([$user['id']]);
            if(!$st->fetch()) { throw new ApiError(401,'UNAUTHENTICATED','Účet už neexistuje.'); }
            $st=$this->db->prepare('SELECT id,request_hash FROM orders WHERE user_id=? AND idempotency_key=?'); $st->execute([$user['id'],$key]);
            $existing=$st->fetch();
            if($existing) {
                if(!hash_equals($existing['request_hash'],$hash)) { throw new ApiError(409,'IDEMPOTENCY_CONFLICT','Tento kľúč už bol použitý pre inú požiadavku.'); }
                $order=$this->get($user,$existing['id']); $this->db->commit();
                return ['order'=>$order,'replayed'=>true];
            }
            $products=$this->catalog->forCart($user['workspace_id'],$data,true);
            $quote=Pricing::quote($data,$products,$this->fault);
            if($data['expected_total_cents']!==$quote['total_cents']) {
                throw new ApiError(409,'PRICE_CHANGED','Suma sa zmenila. Obnovte cenovú ponuku.',['actual_total_cents'=>$quote['total_cents']]);
            }
            $id=Http::uuid(); $number='TL-'.strtoupper(str_replace('-','',$id)); $c=$data['customer'];
            $st=$this->db->prepare('INSERT INTO orders (id,order_number,workspace_id,user_id,status,currency,subtotal_cents,discount_cents,shipping_cents,total_cents,coupon_code,delivery_method,customer_name,customer_email,address_line,city,postal_code,note,idempotency_key,request_hash) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
            $st->execute([$id,$number,$user['workspace_id'],$user['id'],'created','EUR',$quote['subtotal_cents'],
                $quote['discount_cents'],$quote['shipping_cents'],$quote['total_cents'],$quote['coupon_code'],
                $quote['delivery_method'],$c['name'],$c['email'],$c['address'],$c['city'],$c['postal_code'],
                $data['note'],$key,$hash]);
            $insert=$this->db->prepare('INSERT INTO order_items (order_id,product_id,sku,product_name,unit_price_cents,quantity,line_total_cents) VALUES (?,?,?,?,?,?,?)');
            $stock=$this->db->prepare('UPDATE products SET stock=stock-? WHERE id=? AND stock>=?');
            foreach($quote['items'] as $line) {
                $product=$products[$line['sku']];
                $insert->execute([$id,$product['id'],$line['sku'],$line['name'],$line['unit_price_cents'],$line['quantity'],$line['line_total_cents']]);
                if($this->fault!=='stock_not_decremented') {
                    $stock->execute([$line['quantity'],$product['id'],$line['quantity']]);
                    if($stock->rowCount()!==1) { throw new ApiError(409,'OUT_OF_STOCK','Nedostatočné skladové množstvo.'); }
                }
            }
            $order=$this->get($user,$id); $this->db->commit(); return ['order'=>$order,'replayed'=>false];
        } catch(\Throwable $e) { if($this->db->inTransaction()) { $this->db->rollBack(); } throw $e; }
    }
    public function get(array $user,string $id): array
    {
        $st=$this->db->prepare('SELECT * FROM orders WHERE id=? AND user_id=?'); $st->execute([$id,$user['id']]); $order=$st->fetch();
        if(!$order) { throw new ApiError(404,'ORDER_NOT_FOUND','Objednávka neexistuje.'); }
        unset($order['request_hash'],$order['idempotency_key']);
        foreach(['workspace_id','user_id','subtotal_cents','discount_cents','shipping_cents','total_cents'] as $k) { $order[$k]=(int)$order[$k]; }
        $st=$this->db->prepare('SELECT sku,product_name AS name,unit_price_cents,quantity,line_total_cents FROM order_items WHERE order_id=? ORDER BY sku'); $st->execute([$id]);
        $order['items']=$st->fetchAll();
        foreach($order['items'] as &$line) { foreach(['unit_price_cents','quantity','line_total_cents'] as $k) { $line[$k]=(int)$line[$k]; } } unset($line);
        // Deliberate instructor-controlled fault: persisted data remains correct; response does not.
        if($this->fault==='order_total_response') { $order['total_cents']+=100; }
        return $order;
    }
    public function list(array $user,array $query): array
    {
        $page=$query['page']??'1';
        if(!is_string($page)||!ctype_digit($page)||(int)$page<1||(int)$page>100000) { throw new ApiError(422,'VALIDATION_ERROR','Neplatná stránka.'); }
        $offset=((int)$page-1)*20;
        $count=$this->db->prepare('SELECT COUNT(*) FROM orders WHERE user_id=?'); $count->execute([$user['id']]);
        $st=$this->db->prepare('SELECT id,order_number,status,total_cents,currency,created_at FROM orders WHERE user_id=? ORDER BY created_at DESC,id DESC LIMIT 20 OFFSET '.$offset);
        $st->execute([$user['id']]); $rows=$st->fetchAll();
        foreach($rows as &$row) { $row['total_cents']=(int)$row['total_cents']+($this->fault==='order_total_response'?100:0); } unset($row);
        return ['orders'=>$rows,'page'=>(int)$page,'page_size'=>20,'total'=>(int)$count->fetchColumn()];
    }
    public function cancel(array $user,string $id): array
    {
        $this->db->beginTransaction();
        try {
            $st=$this->db->prepare('SELECT status FROM orders WHERE id=? AND user_id=? FOR UPDATE'); $st->execute([$id,$user['id']]); $status=$st->fetchColumn();
            if($status===false) { throw new ApiError(404,'ORDER_NOT_FOUND','Objednávka neexistuje.'); }
            if($status==='created') {
                $st=$this->db->prepare('SELECT product_id,quantity FROM order_items WHERE order_id=? ORDER BY sku'); $st->execute([$id]);
                $restore=$this->db->prepare('UPDATE products SET stock=stock+? WHERE id=?');
                foreach($st->fetchAll() as $item) {
                    if($this->fault!=='stock_not_decremented') { $restore->execute([$item['quantity'],$item['product_id']]); }
                }
                $st=$this->db->prepare("UPDATE orders SET status='cancelled',cancelled_at=UTC_TIMESTAMP() WHERE id=?"); $st->execute([$id]);
            }
            $order=$this->get($user,$id); $this->db->commit(); return $order;
        } catch(\Throwable $e) { if($this->db->inTransaction()) { $this->db->rollBack(); } throw $e; }
    }
}
