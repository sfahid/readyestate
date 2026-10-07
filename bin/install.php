<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/app/bootstrap.php';
try{foreach(explode(';',file_get_contents(dirname(__DIR__).'/database/schema.sql')) as $sql){if(trim($sql)!=='')db()->exec($sql);}echo "Database schema installed. Open the app and use the setup token from config/config.php to create your administrator.\n";}
catch(Throwable $e){fwrite(STDERR,"Installation failed: ".$e->getMessage()."\n");exit(1);}
