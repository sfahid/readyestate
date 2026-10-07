<?php
declare(strict_types=1);
putenv('DB_NAME='.(getenv('DB_NAME')?:'readyestate_test'));
require dirname(__DIR__).'/app/accounting.php';
need(str_ends_with($config['db_name'],'_test'),'Income checks require an isolated test database.');
$n=0;
function verify_income(bool $condition,string $label):void {global $n;if(!$condition)throw new RuntimeException('FAIL: '.$label);$n++;echo "PASS $label\n";}
function reject_income(callable $fn,string $label):void {try{$fn();}catch(DomainException){verify_income(true,$label);return;}throw new RuntimeException('Expected rejection: '.$label);}
$tag=bin2hex(random_bytes(5));$password=bin2hex(random_bytes(15));$email='income-'.$tag.'@example.test';
query('INSERT INTO users(name,email,password_hash) VALUES(?,?,?)',['Income QA',$email,password_hash($password,PASSWORD_DEFAULT)]);
$u=(int)db()->lastInsertId();$c=create_company(['name'=>'Income QA','currency'=>'PKR'],$u)['id'];
$c2=create_company(['name'=>'Separate QA','currency'=>'PKR'],$u)['id'];
$cash=system_account($c,'cash');$bank=system_account($c,'bank');$revenue=system_account($c,'sales');
function post_income(array $data):array {global $u,$c;return mutate(['company'=>$c,'request_key'=>bin2hex(random_bytes(16)),...$data],$u);}
$input=['action'=>'income','date'=>'2026-09-22','memo'=>'Service fee','amount'=>'1250.25','category'=>$revenue,'account_id'=>$bank];
$id=post_income($input)['id'];
$lines=rows('SELECT * FROM journal_lines WHERE journal_id=? ORDER BY id',[$id]);
verify_income(count($lines)===2&&(int)$lines[0]['account_id']===$bank&&(int)$lines[0]['debit']===125025&&(int)$lines[1]['account_id']===$revenue&&(int)$lines[1]['credit']===125025,'Income debits bank and credits revenue with exact paisa');
verify_income(one('SELECT source FROM journals WHERE id=?',[$id])['source']==='income','Income has its own journal source');
verify_income((int)query('SELECT SUM(debit-credit) FROM journal_lines WHERE company_id=? AND account_id=?',[$c,$revenue])->fetchColumn()===-125025,'Income appears in revenue and profit totals');
reject_income(fn()=>post_income([...$input,'category'=>system_account($c,'expenses')]),'Reject expense category');
reject_income(fn()=>post_income([...$input,'account_id'=>system_account($c,'capital')]),'Reject non-cash receiving account');
reject_income(fn()=>post_income([...$input,'account_id'=>system_account($c2,'bank')]),'Reject cross-company bank');
reject_income(fn()=>post_income([...$input,'category'=>system_account($c2,'sales')]),'Reject cross-company revenue account');
reject_income(fn()=>post_income([...$input,'amount'=>'0']),'Reject zero income');
reject_income(fn()=>post_income([...$input,'amount'=>'-10']),'Reject negative income');
verify_income((int)query('SELECT COUNT(*) FROM journals WHERE company_id=?',[$c])->fetchColumn()===1,'Rejected income leaves no partial records');
$key=bin2hex(random_bytes(16));$retry=[...$input,'company'=>$c,'request_key'=>$key,'account_id'=>$cash];
$a=mutate($retry,$u);$b=mutate($retry,$u);
verify_income($a===$b&&(int)query('SELECT COUNT(*) FROM journals WHERE company_id=?',[$c])->fetchColumn()===2,'Income retry posts only once');
post_income(['action'=>'reverse','journal'=>$id,'date'=>'2026-09-22','reason'=>'Income correction']);
verify_income((int)query('SELECT SUM(debit-credit) FROM journal_lines WHERE company_id=? AND account_id=?',[$c,$bank])->fetchColumn()===0,'Income reversal restores bank balance');
reject_income(fn()=>post_income(['action'=>'reverse','journal'=>$id,'date'=>'2026-09-22','reason'=>'Again']),'Prevent repeated income reversal');
post_income(['action'=>'member','name'=>'Viewer','email'=>'viewer-'.$tag.'@example.test','password'=>$password,'role'=>'Viewer']);
$v=(int)one('SELECT id FROM users WHERE email=?',['viewer-'.$tag.'@example.test'])['id'];
reject_income(fn()=>mutate([...$input,'company'=>$c,'request_key'=>bin2hex(random_bytes(16))],$v),'Viewer cannot post income');
$t=one('SELECT SUM(debit) AS d,SUM(credit) AS c FROM journal_lines WHERE company_id=?',[$c]);
verify_income($t['d']===$t['c'],'Income and reversal keep trial balance balanced');
if(getenv('QA_FIXTURE_FILE'))file_put_contents(getenv('QA_FIXTURE_FILE'),json_encode(['email'=>$email,'password'=>$password,'company'=>$c]));
echo "$n income checks passed.\n";

