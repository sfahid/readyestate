<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/accounting.php';
header('Content-Type: application/json; charset=utf-8');
function respond(array $value,int $status=200):never{http_response_code($status);echo json_encode($value,JSON_THROW_ON_ERROR);exit;}
try {
 $method=$_SERVER['REQUEST_METHOD'];
 if($method==='GET'){
  $action=$_GET['action']??'session';
  if($action==='session'){
   $count=(int)query('SELECT COUNT(*) FROM users')->fetchColumn();
   $u=isset($_SESSION['user_id'])?one('SELECT id,name,email FROM users WHERE id=?',[$_SESSION['user_id']]):null;
   $companies=$u?rows('SELECT c.id,c.name,c.currency,m.role FROM companies c JOIN memberships m ON m.company_id=c.id WHERE m.user_id=? ORDER BY c.name',[$u['id']]):[];
   respond(['csrf'=>$_SESSION['csrf'],'user'=>$u,'companies'=>$companies,'needs_setup'=>$count===0,'local_setup_token'=>($count===0 && PHP_SAPI==='cli-server' && in_array($_SERVER['REMOTE_ADDR']??'', ['127.0.0.1','::1'],true) ? $config['setup_token'] : '')]);
  }
  $u=user();
  if($action==='books')respond(snapshot((int)($_GET['company']??0),(int)$u['id']));
  throw new DomainException('Unknown request.');
 }
 need($method==='POST','Unsupported request.');
 need((int)($_SERVER['CONTENT_LENGTH']??0)<=200000,'Request is too large.');
 $b=json_decode(file_get_contents('php://input'),true,128,JSON_THROW_ON_ERROR);
 need(is_array($b),'Invalid request.');
 need(hash_equals($_SESSION['csrf'],(string)($_SERVER['HTTP_X_CSRF_TOKEN']??'')),'Your session expired. Refresh the page.');
 $action=$b['action']??'';
 if($action==='setup'){
  need((int)query('SELECT COUNT(*) FROM users')->fetchColumn()===0,'Setup is already complete.');
  need($config['setup_token']!=='CHANGE-THIS-TO-A-LONG-RANDOM-SECRET'&&strlen($config['setup_token'])>=24&&hash_equals($config['setup_token'],(string)($b['setup_token']??'')),'Enter the setup token from your configuration.');
  $email=strtolower(text_value($b['email']??'',190));need(filter_var($email,FILTER_VALIDATE_EMAIL)!==false,'Enter a valid email.');
  // MySQL advisory lock prevents simultaneous first-admin creation.
  need((int)query("SELECT GET_LOCK('ledgercraft_first_admin',5)")->fetchColumn()===1,'Setup is busy. Try again.');
  try {need((int)query('SELECT COUNT(*) FROM users')->fetchColumn()===0,'Setup is already complete.');query('INSERT INTO users(name,email,password_hash) VALUES(?,?,?)',[text_value($b['name']??'',150),$email,password_hash(password_valid($b['password']??''),PASSWORD_DEFAULT)]);$_SESSION['user_id']=(int)db()->lastInsertId();}
  finally {query("SELECT RELEASE_LOCK('ledgercraft_first_admin')");}
  session_regenerate_id(true);$_SESSION['csrf']=bin2hex(random_bytes(32));respond(['ok'=>true]);
 }
 if($action==='login'){
  $email=strtolower(trim((string)($b['email']??'')));$ip=hash('sha256',$_SERVER['REMOTE_ADDR']??'');$eh=hash('sha256',$email);
  $count=(int)query('SELECT COUNT(*) FROM login_attempts WHERE (ip_hash=? OR email_hash=?) AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)',[$ip,$eh])->fetchColumn();
  need($count<15,'Too many sign-in attempts. Try again in 15 minutes.');
  query('INSERT INTO login_attempts(ip_hash,email_hash) VALUES(?,?)',[$ip,$eh]);
  $u=one('SELECT * FROM users WHERE email=?',[$email]);
  $hash=$u['password_hash']??'$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
  need(password_verify((string)($b['password']??''),$hash)&&$u!==null,'Email or password is incorrect.');
  query('DELETE FROM login_attempts WHERE email_hash=?',[$eh]);
  session_regenerate_id(true);$_SESSION['user_id']=(int)$u['id'];$_SESSION['csrf']=bin2hex(random_bytes(32));respond(['ok'=>true]);
 }
 if($action==='logout'){$_SESSION=[];session_regenerate_id(true);respond(['ok'=>true]);}
 $u=user();
 if($action==='password'){
  $record=one('SELECT password_hash FROM users WHERE id=?',[$u['id']]);
  need(password_verify((string)($b['current_password']??''),$record['password_hash']),'Current password is incorrect.');
  query('UPDATE users SET password_hash=? WHERE id=?',[password_hash(password_valid($b['password']??''),PASSWORD_DEFAULT),$u['id']]);
  session_regenerate_id(true);respond(['ok'=>true]);
 }
 if($action==='company')respond(create_company($b,(int)$u['id']));
 respond(mutate($b,(int)$u['id']));
}catch(DomainException $e){respond(['error'=>$e->getMessage()],422);}
catch(JsonException $e){respond(['error'=>'Invalid request data.'],400);}
catch(Throwable $e){error_log((string)$e);respond(['error'=>'Unable to complete the request. Check the database connection or try again.'],500);}
