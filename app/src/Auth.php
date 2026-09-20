<?php
declare(strict_types=1);
namespace TestLab;
use PDO;

final class Auth
{
    private bool $bearer=false;
    public function __construct(private readonly PDO $db,private readonly array $config) {}
    public function start(): void
    {
        if(session_status()===PHP_SESSION_ACTIVE) { return; }
        ini_set('session.use_strict_mode','1'); ini_set('session.use_only_cookies','1');
        session_name($this->config['session_name']);
        // Scope the cookie to this installation, including a shared-hosting subdirectory.
        $path=rtrim(str_replace('\\','/',dirname(dirname($_SERVER['SCRIPT_NAME']??'/api/index.php'))),'/').'/';
        $https=isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off';
        session_set_cookie_params(['lifetime'=>0,'path'=>$path,
            'secure'=>$this->config['secure_cookies']??$https,'httponly'=>true,'samesite'=>'Lax']);
        session_start();
        if(!isset($_SESSION['csrf'])) { $_SESSION['csrf']=bin2hex(random_bytes(32)); }
    }
    public function csrf(): string { $this->start(); return $_SESSION['csrf']; }
    public function current(bool $required=false): ?array
    {
        $authorization=Http::header('Authorization'); $userId=null;
        if($authorization!=='') {
            $this->bearer=true;
            if(!preg_match('/^Bearer ([a-f0-9]{64})$/D',$authorization,$m)) { throw new ApiError(401,'UNAUTHENTICATED','Neplatný prístupový token.'); }
            $st=$this->db->prepare('SELECT user_id FROM api_tokens WHERE token_hash=? AND expires_at>UTC_TIMESTAMP()');
            $st->execute([hash('sha256',$m[1])]); $id=$st->fetchColumn();
            if($id===false) { throw new ApiError(401,'UNAUTHENTICATED','Token vypršal alebo nie je platný.'); }
            $userId=(int)$id;
        } else {
            $this->start();
            if(isset($_SESSION['user_id']) && (int)($_SESSION['expires_at']??0)>time()) { $userId=(int)$_SESSION['user_id']; }
            elseif(isset($_SESSION['user_id'])) { unset($_SESSION['user_id'],$_SESSION['expires_at']); }
        }
        $user=null;
        if($userId!==null) {
            $st=$this->db->prepare('SELECT u.id,u.workspace_id,u.name,u.email,w.code AS workspace_code FROM users u JOIN workspaces w ON w.id=u.workspace_id WHERE u.id=?');
            $st->execute([$userId]); $user=$st->fetch()?:null;
            if($user) { $user['id']=(int)$user['id']; $user['workspace_id']=(int)$user['workspace_id']; }
        }
        if($required && !$user) { throw new ApiError(401,'UNAUTHENTICATED','Najskôr sa prihláste.'); }
        return $user;
    }
    public function checkCsrf(): void
    {
        if($this->bearer) { return; } // Only after current() validated the bearer token.
        if(!hash_equals($this->csrf(),Http::header('X-CSRF-Token'))) { throw new ApiError(403,'CSRF_FAILED','Neplatný CSRF token. Obnovte stránku.'); }
    }
    public function credentials(array $input): array
    {
        $email=Validation::email($input,'email'); $password=Validation::password($input);
        $ip=$_SERVER['REMOTE_ADDR']??'cli';
        // An IP-wide limiter (120/15m) plus email-wide limiter (10/15m). No X-Forwarded-For trust.
        $keys=[hash('sha256','ip:'.$ip)=>120,hash('sha256','email:'.$email)=>10]; ksort($keys);
        $this->db->beginTransaction();
        try {
            foreach($keys as $key=>$limit) {
                $st=$this->db->prepare('INSERT IGNORE INTO login_limits (bucket,attempts,window_started) VALUES (?,0,UTC_TIMESTAMP())'); $st->execute([$key]);
                $st=$this->db->prepare('SELECT attempts,UNIX_TIMESTAMP(window_started) AS started FROM login_limits WHERE bucket=? FOR UPDATE'); $st->execute([$key]); $row=$st->fetch();
                if((int)$row['started']<=time()-900) {
                    $st=$this->db->prepare('UPDATE login_limits SET attempts=0,window_started=UTC_TIMESTAMP() WHERE bucket=?'); $st->execute([$key]); $row['attempts']=0;
                }
                if((int)$row['attempts']>=$limit) { throw new ApiError(429,'RATE_LIMITED','Priveľa pokusov o prihlásenie. Skúste neskôr.'); }
                $st=$this->db->prepare('UPDATE login_limits SET attempts=attempts+1 WHERE bucket=?'); $st->execute([$key]);
            }
            $this->db->commit();
        } catch(\Throwable $e) { if($this->db->inTransaction()) { $this->db->rollBack(); } throw $e; }
        $st=$this->db->prepare('SELECT * FROM users WHERE email=?'); $st->execute([$email]); $user=$st->fetch();
        // Fixed dummy hash avoids skipping the password hash operation for an unknown user.
        $dummy='$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
        $valid=password_verify($password,$user['password_hash']??$dummy);
        if(!$user||!$valid) { throw new ApiError(401,'INVALID_CREDENTIALS','Nesprávny e-mail alebo heslo.'); }
        // Successful login resets only the account bucket, not the shared IP bucket.
        $st=$this->db->prepare('DELETE FROM login_limits WHERE bucket=?'); $st->execute([hash('sha256','email:'.$email)]);
        return $user;
    }
    public function login(array $input): array
    {
        $this->start(); $this->checkCsrf(); $user=$this->credentials($input);
        session_regenerate_id(true); $_SESSION=['user_id'=>(int)$user['id'],
            'expires_at'=>time()+(int)$this->config['session_ttl_seconds'],'csrf'=>bin2hex(random_bytes(32))];
        return ['user'=>$this->current(true),'csrf_token'=>$this->csrf()];
    }
    public function token(array $input): array
    {
        $user=$this->credentials($input); $token=bin2hex(random_bytes(32));
        $expires=gmdate('Y-m-d H:i:s',time()+(int)$this->config['token_ttl_seconds']);
        $st=$this->db->prepare('INSERT INTO api_tokens (user_id,token_hash,expires_at) VALUES (?,?,?)');
        $st->execute([$user['id'],hash('sha256',$token),$expires]);
        return ['token'=>$token,'token_type'=>'Bearer','expires_at'=>str_replace(' ','T',$expires).'Z'];
    }
    public function logout(): array
    {
        $this->current(true); $this->checkCsrf();
        if($this->bearer) {
            $token=substr(Http::header('Authorization'),7);
            $st=$this->db->prepare('DELETE FROM api_tokens WHERE token_hash=?'); $st->execute([hash('sha256',$token)]);
        } else {
            $_SESSION=[]; session_regenerate_id(true); $_SESSION['csrf']=bin2hex(random_bytes(32));
        }
        return ['logged_out'=>true];
    }
}
