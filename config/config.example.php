<?php
return [
 'db_host' => getenv('DB_HOST') ?: '127.0.0.1',
 'db_port' => getenv('DB_PORT') ?: '3306',
 'db_name' => getenv('DB_NAME') ?: 'readyestate',
 'db_user' => getenv('DB_USER') ?: 'readyestate',
 'db_password' => getenv('DB_PASSWORD') ?: '',
 'setup_token' => getenv('SETUP_TOKEN') ?: 'CHANGE-THIS-TO-A-LONG-RANDOM-SECRET',
 'secure_cookies' => (getenv('APP_HTTPS') ?: '0') === '1',
 'timezone' => 'Asia/Karachi',
];

