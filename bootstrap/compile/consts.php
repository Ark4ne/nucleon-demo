<?php
define('BASE_PATH', __DIR__ . '/../../');
define('APP_ENV', ($_ = getenv('APP_ENV')) === false ? 'development' : $_);
define('APP_DEBUG', true);
define('DB_DRIVER', '\\Phalcon\\Db\\Adapter\\Pdo\\Postgresql');
define('DB_URL', getenv('DATABASE_URL'));
define('SESSION_ID', 'heroku-session-id');
