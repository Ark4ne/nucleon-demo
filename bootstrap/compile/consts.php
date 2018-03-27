<?php
define('BASE_PATH', __DIR__ . '/../../');
define('APP_ENV', ($_ = getenv('APP_ENV')) === false ? 'development' : $_);
define('APP_DEBUG', true);
define('DB_URL', getenv('DATABASE_URL'));
define('DB_HOST', getenv('DATABASE_HOST'));
define('DB_USER', getenv('DATABASE_USER'));
define('DB_PWD', getenv('DATABASE_PASS'));
define('DB_NAME', getenv('DATABASE_DBNAME'));
define('DB_PORT', getenv('DATABASE_PORT'));
define('SESSION_ID', 'heroku-session-id');
