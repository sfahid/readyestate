<?php
declare(strict_types=1);
putenv('DB_NAME=readyestate_test');
require dirname(__DIR__).'/app/accounting.php';
need($config['db_name']==='readyestate_test','Tests must use the isolated test database.');
$passed=0;
function check(bool $v,string $label):void{global $passed;if(!$v)throw new RuntimeException('FAIL: '.$label);$passed++;echo "PASS $label\n";}
function rejects(callable $f,string $label):void{try{$f();}catch(DomainException){check(true,$label);return;}throw new RuntimeException('FAIL expected rejection: '.$label);}
$tag=bin2hex(random_bytes(4));$password=bin2hex(random_bytes(12));
query('INSERT INTO users(name,email,password_hash) VALUES(?,?,?)',['QA Owner','qa-'.$tag.'@example.test',password_hash($password,PASSWORD_DEFAULT)]);
$u=(int)db()->lastInsertId();$c=create_company(['name'=>'Aster Trading · QA','currency'=>'PKR','address'=>'Karachi, Pakistan'],$u)['id'];
$c2=create_company(['name'=>'Second Company · QA','currency'=>'PKR'],$u)['id'];
$cash=system_account($c,'cash');$bank=system_account($c,'bank');$capital=system_account($c,'capital');$expense=system_account($c,'expenses');
function action(array $data):array{global $c,$u;return mutate(['company'=>$c,'request_key'=>bin2hex(random_bytes(16)),...$data],$u);}
check(money('0.29')===29,'Money uses exact integer cents');
check(quantity('1.2345')===12345,'Fractional quantities preserve four decimals');
rejects(fn()=>money('1.001'),'Reject precision beyond two currency decimals');
rejects(fn()=>money('-1'),'Reject negative amounts');
rejects(fn()=>day('2026-02-30'),'Reject impossible dates');
$opening=action(['action'=>'journal','date'=>'2026-09-01','memo'=>'Owner investment','lines'=>[['account_id'=>$bank,'debit'=>'500000','credit'=>'0'],['account_id'=>$capital,'debit'=>'0','credit'=>'500000']]]);
$count=(int)query('SELECT COUNT(*) FROM journals WHERE company_id=?',[$c])->fetchColumn();
rejects(fn()=>action(['action'=>'journal','date'=>'2026-09-01','memo'=>'Unbalanced','lines'=>[['account_id'=>$cash,'debit'=>'10','credit'=>'0'],['account_id'=>$capital,'debit'=>'0','credit'=>'9']]]),'Reject unbalanced journal');
check((int)query('SELECT COUNT(*) FROM journals WHERE company_id=?',[$c])->fetchColumn()===$count,'Rejected entry leaves no partial journal');
rejects(fn()=>action(['action'=>'journal','date'=>'2026-09-01','memo'=>'Mixed company','lines'=>[['account_id'=>system_account($c2,'cash'),'debit'=>'10','credit'=>'0'],['account_id'=>$capital,'debit'=>'0','credit'=>'10']]]),'Reject cross-company account');
rejects(fn()=>action(['action'=>'journal','date'=>'2026-09-01','memo'=>'Direct control','lines'=>[['account_id'=>system_account($c,'receivable'),'debit'=>'10','credit'=>'0'],['account_id'=>$capital,'debit'=>'0','credit'=>'10']]]),'Protect receivable control account');
$sale=action(['action'=>'invoice','kind'=>'sale','party'=>'Northstar Studio','date'=>'2026-09-05','due'=>'2026-09-20','items'=>[['description'=>'Design services','quantity'=>'2.5','price'=>'25000']]]);
$i=one('SELECT * FROM invoices WHERE id=?',[$sale['id']]);check((int)$i['total']===6250000,'Sales invoice calculates fractional quantity correctly');
action(['action'=>'payment','invoice'=>$i['id'],'amount'=>'20000','account_id'=>$bank,'date'=>'2026-09-06']);
$i=one('SELECT * FROM invoices WHERE id=?',[$i['id']]);check((int)$i['paid']===2000000,'Partial payment reduces receivable');
rejects(fn()=>action(['action'=>'payment','invoice'=>$i['id'],'amount'=>'50000','account_id'=>$bank,'date'=>'2026-09-07']),'Reject overpayment');
rejects(fn()=>action(['action'=>'void_invoice','invoice'=>$i['id'],'date'=>'2026-09-07','reason'=>'Test']),'Reject voiding a paid invoice');
$purchase=action(['action'=>'invoice','kind'=>'purchase','party'=>'Metro Supplies','date'=>'2026-09-03','due'=>'2026-09-25','items'=>[['description'=>'Office materials','quantity'=>'10','price'=>'1500']]]);
action(['action'=>'payment','invoice'=>$purchase['id'],'amount'=>'5000','account_id'=>$bank,'date'=>'2026-09-05']);
$expenseEntry=action(['action'=>'expense','date'=>'2026-09-08','memo'=>'Internet and utilities','amount'=>'6500','category'=>$expense,'account_id'=>$bank]);
$key=bin2hex(random_bytes(16));$data=['company'=>$c,'request_key'=>$key,'action'=>'transfer','date'=>'2026-09-10','from'=>$bank,'to'=>$cash,'amount'=>'10000'];
$first=mutate($data,$u);$second=mutate($data,$u);check($first===$second,'Idempotent retry returns the same posting');
$bad=action(['action'=>'expense','date'=>'2026-09-10','memo'=>'Mistaken expense','amount'=>'100','category'=>$expense,'account_id'=>$cash]);
action(['action'=>'reverse','journal'=>$bad['id'],'date'=>'2026-09-11','reason'=>'Duplicate expense']);
rejects(fn()=>action(['action'=>'reverse','journal'=>$bad['id'],'date'=>'2026-09-11','reason'=>'Again']),'Prevent double reversal');
$void=action(['action'=>'invoice','kind'=>'sale','party'=>'Cancelled customer','date'=>'2026-09-10','due'=>'2026-09-12','items'=>[['description'=>'Cancelled work','quantity'=>'1','price'=>'1000']]]);
action(['action'=>'void_invoice','invoice'=>$void['id'],'date'=>'2026-09-11','reason'=>'Cancelled before payment']);
check(one('SELECT status FROM invoices WHERE id=?',[$void['id']])['status']==='void','Unpaid invoice void posts a reversal');
rejects(fn()=>action(['action'=>'payment','invoice'=>$void['id'],'amount'=>'1','account_id'=>$bank,'date'=>'2026-09-12']),'Reject payment to voided invoice');
$trial=one('SELECT SUM(debit) AS d,SUM(credit) AS c FROM journal_lines WHERE company_id=?',[$c]);check($trial['d']===$trial['c'],'Trial balance debits equal credits');
$ar=(int)query('SELECT SUM(debit-credit) FROM journal_lines WHERE company_id=? AND account_id=?',[$c,system_account($c,'receivable')])->fetchColumn();
$arInvoices=(int)query("SELECT SUM(total-paid) FROM invoices WHERE company_id=? AND kind='sale' AND status='posted'",[$c])->fetchColumn();check($ar===$arInvoices,'Receivable control agrees with invoices');
$ap=-(int)query('SELECT SUM(debit-credit) FROM journal_lines WHERE company_id=? AND account_id=?',[$c,system_account($c,'payable')])->fetchColumn();
$apInvoices=(int)query("SELECT SUM(total-paid) FROM invoices WHERE company_id=? AND kind='purchase' AND status='posted'",[$c])->fetchColumn();check($ap===$apInvoices,'Payable control agrees with bills');
check(count(snapshot($c2,$u)['journals'])===0,'Second company has separate books');
$viewerEmail='viewer-'.$tag.'@example.test';action(['action'=>'member','name'=>'QA Viewer','email'=>$viewerEmail,'password'=>$password,'role'=>'Viewer']);
$v=(int)one('SELECT id FROM users WHERE email=?',[$viewerEmail])['id'];
check(snapshot($c,$v)['r']==='Viewer','Viewer can read assigned company');
rejects(fn()=>mutate(['company'=>$c,'request_key'=>bin2hex(random_bytes(16)),'action'=>'expense','date'=>'2026-09-12','memo'=>'Forbidden','amount'=>'10','category'=>$expense,'account_id'=>$bank],$v),'Viewer cannot post');
rejects(fn()=>snapshot($c2,$v),'Membership cannot read another company');
action(['action'=>'member','name'=>'QA Accountant','email'=>'accountant-'.$tag.'@example.test','password'=>$password,'role'=>'Accountant']);
$a=(int)one('SELECT id FROM users WHERE email=?',['accountant-'.$tag.'@example.test'])['id'];
rejects(fn()=>mutate(['company'=>$c,'request_key'=>bin2hex(random_bytes(16)),'action'=>'member','name'=>'Other','email'=>'other@example.test','role'=>'Viewer','password'=>$password],$a),'Accountant cannot manage access');
action(['action'=>'remove_member','user_id'=>$v]);rejects(fn()=>snapshot($c,$v),'Revoked membership immediately loses access');
$badCount=(int)query('SELECT COUNT(*) FROM (SELECT journal_id FROM journal_lines WHERE company_id=? GROUP BY journal_id HAVING SUM(debit)<>SUM(credit)) x',[$c])->fetchColumn();check($badCount===0,'Every individual journal is balanced');
if(!is_dir(dirname(__DIR__).'/var'))mkdir(dirname(__DIR__).'/var',0700,true);$path=dirname(__DIR__).'/var/qa-session.json';file_put_contents($path,json_encode(['email'=>'qa-'.$tag.'@example.test','password'=>$password,'company'=>$c,'user'=>$u]));
echo "\n$passed accounting and authorization checks passed.\n";


