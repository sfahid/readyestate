<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/app/bootstrap.php';
try {
 if((int)db()->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE()')->fetchColumn()>0)
  throw new RuntimeException('Fresh installation requires an empty database. Existing data was not changed.');
 foreach(explode(';',file_get_contents(dirname(__DIR__).'/database/fresh-install.sql')) as $sql)if(trim($sql)!=='')db()->exec($sql);
 echo "Ready Estate database installed. Open the app to create your first administrator.\n";
}catch(Throwable $e){fwrite(STDERR,"Installation failed: ".$e->getMessage()."\n");exit(1);}
