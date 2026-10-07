<?php
declare(strict_types=1);
putenv('DB_NAME=ledgercraft_test');
require dirname(__DIR__).'/app/accounting.php';
if(($argv[1]??'')==='worker'){
 try {mutate(['company'=>(int)$argv[2],'request_key'=>bin2hex(random_bytes(16)),'action'=>'payment','invoice'=>(int)$argv[4],'amount'=>'75','account_id'=>(int)$argv[5],'date'=>'2026-09-22'],(int)$argv[3]);echo 'posted';}
 catch(DomainException $e){echo 'rejected';}
 exit;
}
$fixture=json_decode(file_get_contents(dirname(__DIR__).'/var/qa-session.json'),true);
$c=(int)$fixture['company'];$u=(int)$fixture['user'];$bank=system_account($c,'bank');
$i=mutate(['company'=>$c,'request_key'=>bin2hex(random_bytes(16)),'action'=>'invoice','kind'=>'sale','party'=>'Concurrency test','date'=>'2026-09-22','due'=>'2026-09-22','items'=>[['description'=>'Test','quantity'=>'1','price'=>'100']]],$u)['id'];
$processes=[];
for($n=0;$n<2;$n++){
 $pipes=[];$proc=proc_open([PHP_BINARY,'-d','xdebug.mode=off',__FILE__,'worker',(string)$c,(string)$u,(string)$i,(string)$bank],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
 need(is_resource($proc),'Cannot start test worker.');fclose($pipes[0]);$processes[]=[$proc,$pipes];
}
$results=[];foreach($processes as [$proc,$pipes]){$results[]=trim(stream_get_contents($pipes[1]));$errors=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);need(proc_close($proc)===0,'Worker failed: '.$errors);}
sort($results);need($results===['posted','rejected'],'Concurrent payments must produce exactly one posting.');
need((int)one('SELECT paid FROM invoices WHERE id=?',[$i])['paid']===7500,'Concurrent paid balance incorrect.');
echo "PASS simultaneous payments serialize; overpayment is rejected.\n";
