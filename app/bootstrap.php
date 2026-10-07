<?php
declare(strict_types=1);
$configFile = dirname(__DIR__) . '/config/config.php';
$config = require is_file($configFile) ? $configFile : dirname(__DIR__) . '/config/config.example.php';
date_default_timezone_set($config['timezone'] ?? 'Asia/Karachi');
ini_set('display_errors','0');
ini_set('session.use_strict_mode','1');
session_name('ledgercraft_'.substr(hash('sha256',$config['db_name']),0,8));
session_set_cookie_params(['httponly'=>true,'secure'=>($config['secure_cookies'] || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off')),'samesite'=>'Lax','path'=>'/']);
if (PHP_SAPI !== 'cli') {
 session_start();
 header('X-Content-Type-Options: nosniff');
 header('X-Frame-Options: DENY');
 header('Referrer-Policy: no-referrer');
 header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; connect-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
 header('Cache-Control: no-store');
 if(isset($_SESSION['last_seen']) && time()-$_SESSION['last_seen']>3600){$_SESSION=[];session_regenerate_id(true);}
 $_SESSION['last_seen']=time();
 $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}
function db(): PDO {
 static $pdo;
 global $config;
 if(!$pdo) $pdo=new PDO("mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']};charset=utf8mb4",$config['db_user'],$config['db_password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
 return $pdo;
}
function query(string $sql,array $params=[]): PDOStatement {$s=db()->prepare($sql);$s->execute($params);return $s;}
function rows(string $sql,array $params=[]):array{return query($sql,$params)->fetchAll();}
function one(string $sql,array $params=[]):?array {return query($sql,$params)->fetch() ?: null;}
function need(bool $condition,string $message):void{if(!$condition)throw new DomainException($message);}
function text_value(mixed $v,int $max=250):string {need(is_string($v)&&trim($v)!==''&&mb_strlen($v)<=$max,"Enter text between 1 and $max characters.");return trim($v);}
function day(mixed $v):string {need(is_string($v),'Enter a valid date.');$d=DateTimeImmutable::createFromFormat('!Y-m-d',$v);need($d!==false&&$d->format('Y-m-d')===$v,'Enter a valid date.');return $v;}
function money(mixed $v):int {
 need(is_scalar($v)&&preg_match('/^\d{1,10}(?:\.\d{1,2})?$/D',(string)$v)===1,'Enter a positive amount with up to two decimals.');
 $p=explode('.',(string)$v);$n=(int)$p[0]*100+(int)str_pad($p[1]??'',2,'0');need($n<=100000000000,'Amount exceeds the supported limit.');return $n;
}
function quantity(mixed $v):int {need(is_scalar($v)&&preg_match('/^\d{1,6}(?:\.\d{1,4})?$/D',(string)$v)===1,'Quantity must have up to four decimals.');$p=explode('.',(string)$v);$n=(int)$p[0]*10000+(int)str_pad($p[1]??'',4,'0');need($n>0&&$n<=1000000000,'Quantity must be positive and at most 100,000.');return $n;}
function user():array {$u=isset($_SESSION['user_id'])?one('SELECT id,name,email FROM users WHERE id=?',[$_SESSION['user_id']]):null;need($u!==null,'Please sign in.');return $u;}
function role(int $company,int $user):string {$m=one('SELECT role FROM memberships WHERE company_id=? AND user_id=?',[$company,$user]);need($m!==null,'You do not have access to this company.');return $m['role'];}
function audit(int $company,int $user,string $action,string $detail):void{query('INSERT INTO audit_events(company_id,user_id,action,detail) VALUES(?,?,?,?)',[$company,$user,$action,mb_substr($detail,0,250)]);}
function password_valid(mixed $p):string {need(is_string($p)&&strlen($p)>=12&&strlen($p)<=72,'Use a password between 12 and 72 characters.');return $p;}
