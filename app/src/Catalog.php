<?php
declare(strict_types=1);
namespace TestLab;
use PDO;

final class Catalog
{
    public function __construct(private readonly PDO $db) {}
    public function demoWorkspace(): int
    {
        $id=$this->db->query("SELECT id FROM workspaces WHERE code='demo'")->fetchColumn();
        if ($id===false) { throw new ApiError(503,'NOT_INITIALIZED','Databáza ešte nemá výučbové dáta. Spustite inštaláciu.'); }
        return (int)$id;
    }
    public function list(int $workspace, array $query): array
    {
        $q=Validation::text($query,'q',100,false);
        $category=Validation::text($query,'category',40,false);
        $stock=$query['in_stock']??'0';
        if (!is_string($stock)||!in_array($stock,['0','1'],true)) { throw new ApiError(422,'VALIDATION_ERROR','Neplatný filter skladu.'); }
        $sql='SELECT sku,name,description,category,price_cents,stock,active FROM products WHERE workspace_id=? AND active=1';
        $args=[$workspace];
        if ($q!=='') {
            // LOCATE treats % and _ literally; the user input is never part of SQL syntax.
            $sql.=' AND (LOCATE(?,name)>0 OR LOCATE(?,sku)>0)'; $args[]=$q; $args[]=$q;
        }
        if ($category!=='') { $sql.=' AND category=?'; $args[]=$category; }
        if ($stock==='1') { $sql.=' AND stock>0'; }
        $sql.=' ORDER BY id';
        $st=$this->db->prepare($sql); $st->execute($args); $rows=$st->fetchAll();
        foreach ($rows as &$row) { foreach(['price_cents','stock','active'] as $k) { $row[$k]=(int)$row[$k]; } } unset($row);
        return $rows;
    }
    /** Lock in a deterministic SKU order when called inside an order transaction. */
    public function forCart(int $workspace, array $cart, bool $lock=false): array
    {
        $skus=array_column($cart['items'],'sku'); sort($skus,SORT_STRING); $products=[];
        $stmt=$this->db->prepare('SELECT * FROM products WHERE workspace_id=? AND sku=?'.($lock?' FOR UPDATE':''));
        foreach($skus as $sku) { $stmt->execute([$workspace,$sku]); $row=$stmt->fetch(); if($row) { $products[$sku]=$row; } }
        return $products;
    }
}
