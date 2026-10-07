<?php
declare(strict_types=1);

function crm_now():string{return date('Y-m-d H:i:s');}
function crm_datetime(mixed $v):string {
 need(is_string($v),'Enter a date and time.');$value=str_replace('T',' ',$v);
 if(strlen($value)===16)$value.=':00';
 $d=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$value);
 need($d!==false&&$d->format('Y-m-d H:i:s')===$value,'Enter a valid date and time.');return $value;
}
function crm_event(int $c,int $d,int $u,string $kind,string $title,string $detail='',?string $date=null):void {
 query('INSERT INTO re_events(company_id,deal_id,kind,title,detail,event_date,created_by) VALUES(?,?,?,?,?,?,?)',[$c,$d,$kind,mb_substr($title,0,150),$detail,$date??date('Y-m-d'),$u]);
}
function crm_document_bytes(string $filename,string $bytes):array {
 need(strlen($bytes)>0&&strlen($bytes)<=2*1024*1024,'Choose a file up to 2 MB.');
 $filename=basename(str_replace('\\','/',$filename));$ext=strtolower(pathinfo($filename,PATHINFO_EXTENSION));
 $mime=(new finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
 $allowed=['pdf'=>'application/pdf','png'=>'image/png','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','txt'=>'text/plain'];
 need(isset($allowed[$ext])&&$allowed[$ext]===$mime,'Use a PDF, PNG, JPG or plain text file with matching content.');
 $clean=preg_replace('/[^a-zA-Z0-9._ -]/','_',mb_substr($filename,0,180));return [$clean,$mime,strlen($bytes),hash('sha256',$bytes)];
}
function crm_mutate(array $b,int $c,int $u):array {
 $action=$b['action'];
 if($action==='crm_note'){
  $d=estate_deal($c,(int)($b['deal_id']??0));$kind=$b['kind']??'';need(in_array($kind,['note','milestone','call','meeting'],true),'Choose an activity type.');
  $date=day($b['date']??'');need($date>=$d['deal_date'],'Activity cannot precede the deal date.');
  crm_event($c,(int)$d['id'],$u,$kind,text_value($b['title']??'',150),estate_optional($b,'detail',4000),$date);audit($c,$u,'crm.activity',$d['reference'].' · '.$kind);return ['ok'=>true];
 }
 if($action==='crm_document'){
  $d=estate_deal($c,(int)($b['deal_id']??0));$file=$_FILES['document']??null;
  need($file&&($file['error']??1)===UPLOAD_ERR_OK&&is_uploaded_file($file['tmp_name']),'Upload a document up to 2 MB.');
  need((int)$file['size']<=2*1024*1024,'Maximum file size is 2 MB.');
  $bytes=file_get_contents($file['tmp_name']);need($bytes!==false,'Could not read the document.');
  [$name,$mime,$size,$hash]=crm_document_bytes($file['name'],$bytes);$title=text_value($b['title']??'',150);
  query('INSERT INTO re_documents(company_id,deal_id,title,filename,mime,file_size,sha256,content,created_by) VALUES(?,?,?,?,?,?,?,?,?)',[$c,$d['id'],$title,$name,$mime,$size,$hash,$bytes,$u]);$id=(int)db()->lastInsertId();
  crm_event($c,(int)$d['id'],$u,'document','Document attached: '.$title,$name);audit($c,$u,'crm.document',$d['reference'].' · '.$title);return ['id'=>$id];
 }
 if($action==='crm_task'){
  $d=estate_deal($c,(int)($b['deal_id']??0));$assignee=(int)($b['assigned_to']??$u);
  need(one("SELECT user_id FROM memberships WHERE company_id=? AND user_id=? AND role IN ('Owner','Accountant')",[$c,$assignee])!==null,'Assign the task to an owner or accountant in this company.');
  $due=crm_datetime($b['due_at']??'');$remind=crm_datetime($b['remind_at']??'');need($remind<=$due,'Reminder cannot be later than the task due time.');
  $priority=$b['priority']??'normal';need(in_array($priority,['normal','high'],true),'Choose a priority.');
  $title=text_value($b['title']??'',150);
  query('INSERT INTO re_tasks(company_id,deal_id,title,detail,assigned_to,due_at,remind_at,priority,created_by) VALUES(?,?,?,?,?,?,?,?,?)',[$c,$d['id'],$title,estate_optional($b,'detail',2000),$assignee,$due,$remind,$priority,$u]);$id=(int)db()->lastInsertId();
  crm_event($c,(int)$d['id'],$u,'task','Task added: '.$title,'Due '.$due);audit($c,$u,'crm.task',$d['reference'].' · '.$title);return ['id'=>$id];
 }
 if($action==='crm_task_reschedule'){
  $task=one('SELECT * FROM re_tasks WHERE company_id=? AND id=?',[$c,(int)($b['task_id']??0)]);need($task!==null&&$task['status']==='pending','Only pending tasks can be rescheduled.');
  $due=crm_datetime($b['due_at']??'');$remind=crm_datetime($b['remind_at']??'');need($remind<=$due,'Reminder cannot be later than the task due time.');
  $reason=text_value($b['reason']??'',500);
  query('UPDATE re_tasks SET due_at=?,remind_at=? WHERE company_id=? AND id=?',[$due,$remind,$c,$task['id']]);
  crm_event($c,(int)$task['deal_id'],$u,'task','Task rescheduled: '.$task['title'],'Due '.$due.' · '.$reason);audit($c,$u,'crm.reschedule','Task '.$task['id'].' · '.$due);return ['ok'=>true];
 }
 if($action==='crm_task_status'){
  $task=one('SELECT * FROM re_tasks WHERE company_id=? AND id=?',[$c,(int)($b['task_id']??0)]);need($task!==null,'Task not found.');
  $status=$b['status']??'';need(in_array($status,['pending','done','cancelled'],true),'Choose a task status.');
  query('UPDATE re_tasks SET status=?,completed_at=? WHERE company_id=? AND id=?',[$status,$status==='done'?crm_now():null,$c,$task['id']]);
  crm_event($c,(int)$task['deal_id'],$u,'task','Task '.$status.': '.$task['title'],text_value($b['reason']??'',500));audit($c,$u,'crm.task_status','Task '.$task['id'].' · '.$status);return ['ok'=>true];
 }
 throw new DomainException('Unknown follow-up action.');
}
function crm_record_status(array $b,int $c,int $u):void {
 if(($b['action']??'')==='estate_status'){
  $d=estate_deal($c,(int)$b['deal_id']);crm_event($c,(int)$d['id'],$u,'status','Deal '.$d['status'],(string)($b['reason']??''));
 }
}
function crm_snapshot(int $c):array {
 global $config;
 return [
  'now'=>crm_now(),'timezone'=>$config['timezone']??'Asia/Karachi',
  'events'=>rows("SELECT e.*,u.name author FROM re_events e JOIN users u ON u.id=e.created_by WHERE e.company_id=? AND e.kind<>'message' ORDER BY e.event_date DESC,e.id DESC",[$c]),
  'tasks'=>rows('SELECT t.*,u.name assignee,d.reference deal_reference FROM re_tasks t JOIN users u ON u.id=t.assigned_to JOIN re_deals d ON d.id=t.deal_id WHERE t.company_id=? ORDER BY t.due_at,t.id',[$c]),
  'documents'=>rows('SELECT id,deal_id,title,filename,mime,file_size,sha256,created_at FROM re_documents WHERE company_id=? ORDER BY id DESC',[$c]),
  'assignees'=>rows("SELECT u.id,u.name FROM memberships m JOIN users u ON u.id=m.user_id WHERE m.company_id=? AND m.role IN ('Owner','Accountant') ORDER BY u.name",[$c])
 ];
}
