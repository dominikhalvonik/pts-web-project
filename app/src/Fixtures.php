<?php
declare(strict_types=1);
namespace TestLab;
use PDO;

final class Fixtures
{
    public function __construct(private readonly PDO $db) {}
    public static function products(): array
    {
        return [
            ['KB-001','Mechanická klávesnica','Kompaktná klávesnica s USB pripojením.','Periférie',3490,100],
            ['MS-001','Bezdrôtová myš','Ľahká ergonomická myš na každodennú prácu.','Periférie',1990,100],
            ['USB-001','USB-C kábel','Dvojmetrový kábel na nabíjanie a prenos dát.','Príslušenstvo',1250,100],
            ['MON-001','Monitor 24 palcov','IPS monitor do pracovného a študijného prostredia.','Monitory',14900,20],
            ['CAM-001','Webová kamera','Kamera s krytkou objektívu na videohovory.','Periférie',5900,40],
            ['PAD-001','Podložka pod myš','Protišmyková podložka v minimalistickom dizajne.','Príslušenstvo',995,100],
            ['HUB-001','USB-C rozbočovač','Rozbočovač s viacerými portmi. Aktuálne vypredaný.','Príslušenstvo',2990,0],
            ['BAG-001','Obal na notebook','Ochranný obal na notebook s uhlopriečkou 14 palcov.','Príslušenstvo',2500,30],
        ];
    }
    public function create(string $code,string $name,string $email,string $password): array
    {
        if(!preg_match('/^(demo|run-[a-f0-9]{24})$/D',$code)) { throw new \InvalidArgumentException('Invalid workspace code.'); }
        $this->db->beginTransaction();
        try {
            $st=$this->db->prepare('INSERT INTO workspaces (code,name) VALUES (?,?)'); $st->execute([$code,$name]); $wid=(int)$this->db->lastInsertId();
            $this->addUser($wid,$name,$email,$password);
            $st=$this->db->prepare('INSERT INTO products (workspace_id,sku,name,description,category,price_cents,stock) VALUES (?,?,?,?,?,?,?)');
            foreach(self::products() as $p) { $st->execute([$wid,...$p]); }
            $this->db->commit(); return ['workspace_code'=>$code,'email'=>$email,'password'=>$password];
        } catch(\Throwable $e) { if($this->db->inTransaction()) { $this->db->rollBack(); } throw $e; }
    }
    public function addUser(int $workspace,string $name,string $email,string $password): void
    {
        $st=$this->db->prepare('INSERT INTO users (workspace_id,name,email,password_hash) VALUES (?,?,?,?)');
        $st->execute([$workspace,$name,$email,password_hash($password,PASSWORD_DEFAULT)]);
    }
    public function createRun(): array
    {
        $code='run-'.bin2hex(random_bytes(12));
        return $this->create($code,'Izolovaný test',$code.'@example.test',bin2hex(random_bytes(12)));
    }
    public function deleteRun(string $code): array
    {
        // No endpoint can delete the shared demo workspace.
        if(!preg_match('/^run-[a-f0-9]{24}$/D',$code)) { throw new ApiError(422,'INVALID_WORKSPACE','Odstrániť možno iba izolované run-* prostredie.'); }
        $this->db->beginTransaction();
        try {
            $st=$this->db->prepare('SELECT id FROM workspaces WHERE code=? FOR UPDATE'); $st->execute([$code]); $id=$st->fetchColumn();
            if($id!==false) {
                // Explicit order prevents FK/cascade ordering surprises.
                foreach(['orders','users','products'] as $table) { $st=$this->db->prepare("DELETE FROM {$table} WHERE workspace_id=?"); $st->execute([$id]); }
                $st=$this->db->prepare('DELETE FROM workspaces WHERE id=?'); $st->execute([$id]);
            }
            $this->db->commit(); return ['deleted'=>$id!==false,'workspace_code'=>$code];
        } catch(\Throwable $e) { if($this->db->inTransaction()) { $this->db->rollBack(); } throw $e; }
    }
}
