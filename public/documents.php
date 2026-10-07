<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/accounting.php';
try {
 $u=user();
 if($_SERVER['REQUEST_METHOD']==='GET'){
  $c=(int)($_GET['company']??0);role($c,(int)$u['id']);
  $file=one('SELECT filename,mime,content FROM re_documents WHERE company_id=? AND id=?',[$c,(int)($_GET['id']??0)]);need($file!==null,'Document not found.');
  header('Content-Type: '.$file['mime']);header('Content-Disposition: attachment; filename="'.$file['filename'].'"');header('Content-Length: '.strlen($file['content']));echo $file['content'];exit;
 }
 need($_SERVER['REQUEST_METHOD']==='POST','Unsupported request.');
 need(hash_equals($_SESSION['csrf'],(string)($_SERVER['HTTP_X_CSRF_TOKEN']??'')),'Your session expired. Refresh the page.');
 header('Content-Type: application/json');echo json_encode(mutate([...$_POST,'action'=>'crm_document'],(int)$u['id']),JSON_THROW_ON_ERROR);
}catch(DomainException $e){http_response_code(422);header('Content-Type: application/json');echo json_encode(['error'=>$e->getMessage()]);}
catch(Throwable $e){error_log((string)$e);http_response_code(500);header('Content-Type: application/json');echo json_encode(['error'=>'Document could not be processed.']);}
