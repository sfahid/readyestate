<?php
declare(strict_types=1);
require_once __DIR__.'/bootstrap.php';
require_once __DIR__.'/realestate.php';
require_once __DIR__.'/engagement.php';
function account(int $c,int $id):array {$a=one('SELECT * FROM accounts WHERE company_id=? AND id=?',[$c,$id]);need($a!==null,'Account not found in this company.');return $a;}
function system_account(int $c,string $key):int {$a=one('SELECT id FROM accounts WHERE company_id=? AND system_key=?',[$c,$key]);need($a!==null,'Required account is missing.');return (int)$a['id'];}
function cash_account(int $c,int $id):void {need(in_array(account($c,$id)['type'],['Cash','Bank'],true),'Choose a cash or bank account.');}
function post_journal(int $c,int $u,string $date,string $memo,string $source,array $lines,?int $reversal=null):int {
 need(db()->inTransaction(),'Posting requires a transaction.');
 need(count($lines)>=2&&count($lines)<=100,'Use between 2 and 100 journal lines.');
 $dr=0;$cr=0;
 foreach($lines as $l){account($c,(int)$l['account_id']);$d=$l['debit'];$r=$l['credit'];need(is_int($d)&&is_int($r)&&$d>=0&&$r>=0&&(($d>0 xor $r>0))&&$d<=100000000000&&$r<=100000000000,'Every journal line must have either a debit or a credit.');$dr+=$d;$cr+=$r;}
 need($dr===$cr&&$dr>0,'Total debits and credits must balance.');
 $co=one('SELECT next_journal FROM companies WHERE id=? FOR UPDATE',[$c]);need($co!==null,'Company not found.');
 $number='JE-'.str_pad((string)$co['next_journal'],6,'0',STR_PAD_LEFT);
 query('UPDATE companies SET next_journal=next_journal+1 WHERE id=?',[$c]);
 query('INSERT INTO journals(company_id,number,entry_date,memo,source,created_by,reversal_of) VALUES(?,?,?,?,?,?,?)',[$c,$number,day($date),text_value($memo),$source,$u,$reversal]);
 $id=(int)db()->lastInsertId();
 foreach($lines as $l)query('INSERT INTO journal_lines(company_id,journal_id,account_id,debit,credit) VALUES(?,?,?,?,?)',[$c,$id,$l['account_id'],$l['debit'],$l['credit']]);
 audit($c,$u,'journal.post',$number.' · '.$memo);
 return $id;
}
function pair(int $debit,int $credit,int $amount):array{return [['account_id'=>$debit,'debit'=>$amount,'credit'=>0],['account_id'=>$credit,'debit'=>0,'credit'=>$amount]];}
function create_company(array $b,int $u):array {
 $name=text_value($b['name']??'',150);$currency=$b['currency']??'PKR';
 need(in_array($currency,['PKR','USD','EUR','GBP','AED','SAR'],true),'Choose a supported currency.');
 db()->beginTransaction();
 try {
 query('INSERT INTO companies(name,currency,address,owner_id) VALUES(?,?,?,?)',[$name,$currency,mb_substr((string)($b['address']??''),0,1000),$u]);
 $c=(int)db()->lastInsertId();query("INSERT INTO memberships VALUES(?,?,'Owner')",[$c,$u]);
 $list=[['1000','Cash on hand','Cash','cash'],['1010','Bank account','Bank','bank'],['1100','Accounts receivable','Asset','receivable'],['2000','Accounts payable','Liability','payable'],['3000','Owner capital','Equity','capital'],['4000','Sales revenue','Revenue','sales'],['5000','Purchases','Expense','purchases'],['5100','General expenses','Expense','expenses']];
 foreach($list as $a)query('INSERT INTO accounts(company_id,code,name,type,system_key) VALUES(?,?,?,?,?)',[$c,...$a]);
 audit($c,$u,'company.create',$name);db()->commit();return ['id'=>$c];
 }catch(Throwable $e){db()->rollBack();throw $e;}
}
function mutate(array $b,int $u):array {
 $c=(int)($b['company']??0);$action=(string)($b['action']??'');$key=(string)($b['request_key']??'');
 need(preg_match('/^[a-zA-Z0-9-]{16,64}$/D',$key)===1,'A request identifier is required. Refresh and try again.');
 db()->beginTransaction();
 try {
 need(one('SELECT id FROM companies WHERE id=? FOR UPDATE',[$c])!==null,'Company not found.');
 $r=role($c,$u);need($r!=='Viewer','Your role is read-only.');
 $previous=one('SELECT response,user_id FROM requests WHERE company_id=? AND request_key=?',[$c,$key]);
 if($previous){need((int)$previous['user_id']===$u,'Request identifier already used.');db()->commit();return json_decode($previous['response'],true);}
 $result=['ok'=>true];
 switch($action){
 case 'journal':
 $lines=$b['lines']??[];need(is_array($lines),'Add journal lines.');$clean=[];
 foreach($lines as $l){$a=account($c,(int)($l['account_id']??0));need(!in_array($a['system_key'],['receivable','payable','re_client_funds'],true),'Use the relevant invoice, payment or real estate action for control accounts.');
 $clean[]=['account_id'=>(int)$a['id'],'debit'=>money($l['debit']?:'0'),'credit'=>money($l['credit']?:'0')];}
 $result['id']=post_journal($c,$u,day($b['date']??''),text_value($b['memo']??''),'manual',$clean);break;
 case 'invoice':
 $kind=$b['kind']??'';need(in_array($kind,['sale','purchase'],true),'Invalid invoice type.');
 $date=day($b['date']??'');$due=day($b['due']??'');need($due>=$date,'Due date cannot precede the invoice date.');
 $party=text_value($b['party']??'',150);$items=$b['items']??[];need(is_array($items)&&count($items)>0&&count($items)<=100,'Add between 1 and 100 items.');
 $total=0;$clean=[];
 foreach($items as $item){$q=quantity($item['quantity']??'');$price=money($item['price']??'');need($price<=intdiv(PHP_INT_MAX-5000,$q),'Item amount is too large.');$sum=intdiv($q*$price+5000,10000);need($sum>0&&$sum<=100000000000,'Each item must have a positive total.');$total+=$sum;$clean[]=[text_value($item['description']??''),$q,$price,$sum];}
 need($total<=100000000000,'Invoice total is too large.');
 $co=one('SELECT next_invoice FROM companies WHERE id=?',[$c]);$number=($kind==='sale'?'INV-':'BILL-').str_pad((string)$co['next_invoice'],6,'0',STR_PAD_LEFT);
 query('UPDATE companies SET next_invoice=next_invoice+1 WHERE id=?',[$c]);
 $j=post_journal($c,$u,$date,$number.' · '.$party,'invoice',pair(system_account($c,$kind==='sale'?'receivable':'purchases'),system_account($c,$kind==='sale'?'sales':'payable'),$total));
 query('INSERT INTO invoices(company_id,number,kind,party,invoice_date,due_date,notes,total,journal_id,created_by) VALUES(?,?,?,?,?,?,?,?,?,?)',[$c,$number,$kind,$party,$date,$due,mb_substr((string)($b['notes']??''),0,2000),$total,$j,$u]);
 $id=(int)db()->lastInsertId();foreach($clean as $x)query('INSERT INTO invoice_items(invoice_id,description,quantity_units,unit_price,total) VALUES(?,?,?,?,?)',[$id,...$x]);
 $result['id']=$id;break;
 case 'payment':
 $i=one('SELECT * FROM invoices WHERE company_id=? AND id=? FOR UPDATE',[$c,(int)($b['invoice']??0)]);need($i!==null&&$i['status']==='posted','Invoice not found or voided.');
 $n=money($b['amount']??'');need($n>0&&$n<=(int)$i['total']-(int)$i['paid'],'Payment must be positive and cannot exceed the balance.');
 $a=(int)($b['account_id']??0);cash_account($c,$a);$date=day($b['date']??'');need($date>=$i['invoice_date'],'Payment cannot precede the invoice date.');
 $sale=$i['kind']==='sale';$j=post_journal($c,$u,$date,($sale?'Receipt':'Payment').' · '.$i['number'],'payment',pair($sale?$a:system_account($c,'payable'),$sale?system_account($c,'receivable'):$a,$n));
 query('INSERT INTO payments(company_id,invoice_id,journal_id,account_id,payment_date,amount) VALUES(?,?,?,?,?,?)',[$c,$i['id'],$j,$a,$date,$n]);
 query('UPDATE invoices SET paid=paid+? WHERE company_id=? AND id=?',[$n,$c,$i['id']]);break;
 case 'income':
 $a=(int)($b['account_id']??0);cash_account($c,$a);$category=(int)($b['category']??0);need(account($c,$category)['type']==='Revenue','Choose an income (revenue) account.');
 $result['id']=post_journal($c,$u,day($b['date']??''),text_value($b['memo']??''),'income',pair($a,$category,money($b['amount']??'')));break;
 case 'expense':
 $a=(int)($b['account_id']??0);cash_account($c,$a);$category=(int)($b['category']??0);need(account($c,$category)['type']==='Expense','Choose an expense category.');
 $result['id']=post_journal($c,$u,day($b['date']??''),text_value($b['memo']??''),'expense',pair($category,$a,money($b['amount']??'')));break;
 case 'transfer':
 $from=(int)($b['from']??0);$to=(int)($b['to']??0);cash_account($c,$from);cash_account($c,$to);need($from!==$to,'Choose two different accounts.');
 $result['id']=post_journal($c,$u,day($b['date']??''),'Transfer · '.account($c,$from)['name'].' to '.account($c,$to)['name'],'transfer',pair($to,$from,money($b['amount']??'')));break;
 case 'reverse':
 $j=one('SELECT * FROM journals WHERE company_id=? AND id=?',[$c,(int)($b['journal']??0)]);need($j!==null&&in_array($j['source'],['manual','income','expense','transfer'],true),'Only manual entries, income, expenses, and transfers can be reversed here.');
 need(!one('SELECT id FROM journals WHERE reversal_of=?',[$j['id']]),'This entry has already been reversed.');
 $date=day($b['date']??'');need($date>=$j['entry_date'],'Reversal date cannot precede the original entry.');
 $lines=rows('SELECT account_id,credit AS debit,debit AS credit FROM journal_lines WHERE journal_id=?',[$j['id']]);foreach($lines as &$l){$l['debit']=(int)$l['debit'];$l['credit']=(int)$l['credit'];}unset($l);
 $result['id']=post_journal($c,$u,$date,'Reversal · '.$j['number'].' · '.text_value($b['reason']??'',150),'reversal',$lines,(int)$j['id']);break;
 case 'void_invoice':
 $i=one('SELECT * FROM invoices WHERE company_id=? AND id=?',[$c,(int)($b['invoice']??0)]);need($i!==null&&$i['status']==='posted'&&(int)$i['paid']===0,'Only unpaid invoices can be voided.');
 $date=day($b['date']??'');need($date>=$i['invoice_date'],'Void date cannot precede the invoice.');
 $lines=rows('SELECT account_id,credit AS debit,debit AS credit FROM journal_lines WHERE journal_id=?',[$i['journal_id']]);foreach($lines as &$l){$l['debit']=(int)$l['debit'];$l['credit']=(int)$l['credit'];}unset($l);
 post_journal($c,$u,$date,'Void · '.$i['number'].' · '.text_value($b['reason']??'',150),'reversal',$lines,(int)$i['journal_id']);
 query("UPDATE invoices SET status='void' WHERE id=? AND company_id=?",[$i['id'],$c]);break;
 case 'account':
 $code=text_value($b['code']??'',12);need(preg_match('/^[0-9]{4,12}$/D',$code)===1,'Use a unique 4–12 digit account code.');
 $type=$b['type']??'';need(in_array($type,['Asset','Cash','Bank','Liability','Equity','Revenue','Expense'],true),'Choose an account type.');
 need(!one('SELECT id FROM accounts WHERE company_id=? AND code=?',[$c,$code]),'Account code is already in use.');
 query('INSERT INTO accounts(company_id,code,name,type) VALUES(?,?,?,?)',[$c,$code,text_value($b['name']??'',150),$type]);audit($c,$u,'account.create',$code);break;
 case 'member':
 need($r==='Owner','Only the company owner can manage users.');
 $email=strtolower(text_value($b['email']??'',190));need(filter_var($email,FILTER_VALIDATE_EMAIL)!==false,'Enter a valid email.');
 $targetRole=$b['role']??'';need(in_array($targetRole,['Accountant','Viewer'],true),'Choose Accountant or Viewer.');
 $target=one('SELECT id FROM users WHERE email=?',[$email]);
 if(!$target){$password=password_valid($b['password']??'');query('INSERT INTO users(name,email,password_hash) VALUES(?,?,?)',[text_value($b['name']??'',150),$email,password_hash($password,PASSWORD_DEFAULT)]);$target=['id'=>(int)db()->lastInsertId()];}
 need(!one("SELECT user_id FROM memberships WHERE company_id=? AND user_id=? AND role='Owner'",[$c,$target['id']]),'The owner role cannot be changed.');
 query('INSERT INTO memberships(company_id,user_id,role) VALUES(?,?,?) ON DUPLICATE KEY UPDATE role=VALUES(role)',[$c,$target['id'],$targetRole]);
 audit($c,$u,'member.save',$email.' · '.$targetRole);break;
 case 'remove_member':
 need($r==='Owner','Only the company owner can manage users.');
 query("DELETE FROM memberships WHERE company_id=? AND user_id=? AND role<>'Owner'",[$c,(int)($b['user_id']??0)]);audit($c,$u,'member.remove','User '.(int)($b['user_id']??0));break;
 default:
 if(str_starts_with($action,'crm_')){$result=crm_mutate($b,$c,$u);break;}
 if(str_starts_with($action,'estate_')){$result=estate_mutate($b,$c,$u);break;}
 throw new DomainException('Unknown action.');
 }
 crm_record_status($b,$c,$u);
 query('INSERT INTO requests(company_id,request_key,user_id,response) VALUES(?,?,?,?)',[$c,$key,$u,json_encode($result,JSON_THROW_ON_ERROR)]);
 db()->commit();return $result;
 }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
}
function snapshot(int $c,int $u):array {
 $r=role($c,$u);$company=one('SELECT id,name,currency,address FROM companies WHERE id=?',[$c]);
 $accounts=rows('SELECT a.*,COALESCE(SUM(l.debit),0) AS debit,COALESCE(SUM(l.credit),0) AS credit FROM accounts a LEFT JOIN journal_lines l ON l.account_id=a.id AND l.company_id=a.company_id WHERE a.company_id=? GROUP BY a.id ORDER BY a.code',[$c]);
 $journals=rows('SELECT j.*,u.name AS author,(SELECT id FROM journals r WHERE r.reversal_of=j.id) AS reversed_by FROM journals j JOIN users u ON u.id=j.created_by WHERE j.company_id=? ORDER BY j.entry_date DESC,j.id DESC',[$c]);
 $lines=rows('SELECT l.*,a.code,a.name AS account_name FROM journal_lines l JOIN accounts a ON a.id=l.account_id WHERE l.company_id=? ORDER BY l.id',[$c]);
 $invoices=rows('SELECT * FROM invoices WHERE company_id=? ORDER BY invoice_date DESC,id DESC',[$c]);
 $items=rows('SELECT t.* FROM invoice_items t JOIN invoices i ON i.id=t.invoice_id WHERE i.company_id=? ORDER BY t.id',[$c]);
 $payments=rows('SELECT p.*,a.name AS account_name FROM payments p JOIN accounts a ON a.id=p.account_id WHERE p.company_id=? ORDER BY p.payment_date,p.id',[$c]);
 $members=$r==='Owner'?rows('SELECT u.id,u.name,u.email,m.role FROM memberships m JOIN users u ON u.id=m.user_id WHERE m.company_id=? ORDER BY u.name',[$c]):[];
 $audit=rows('SELECT a.*,u.name FROM audit_events a JOIN users u ON u.id=a.user_id WHERE a.company_id=? ORDER BY a.id DESC LIMIT 100',[$c]);
 $estate=estate_snapshot($c);$crm=crm_snapshot($c);
 return compact('crm','estate','company','accounts','journals','lines','invoices','items','payments','members','audit','r');
}

