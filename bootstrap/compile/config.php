<?php
$config = [];
$config['app'] = ['base_uri' => '/'];
$config['assets'] = ['sass' => ['files' => ['resources/assets/sass/app/app.scss' => 'public/css/app.css', 'resources/assets/sass/frontend/frontend.scss' => 'public/css/frontend.css', 'resources/assets/sass/backend/backend.scss' => 'public/css/backend.css'], 'options' => ['style' => 'compressed', 'sourcemap' => 'none']], 'js' => ['compile' => ['directories' => ['resources/assets/js'], 'level' => 'ADVANCED_OPTIMIZATIONS', 'externs_url' => ['http://code.jquery.com/jquery-3.3.1.js', 'https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0-beta/js/materialize.js']], 'precompilations' => [\Neutrino\Assets\Closure\JqueryIdPrecompilation::class, \Neutrino\Assets\Closure\DebugPrecompilation::class => ['debug' => \APP_DEBUG], \Neutrino\Assets\Closure\GlobalClosurePrecompilation::class => ['window' => 'window', 'document' => 'document', 'jQuery' => '$', 'M' => 'M']], 'output_file' => 'public/js/app.js']];
$config['auth'] = ['model' => \App\Core\Models\User::class];
$config['cache'] = ['default' => 'file', 'stores' => ['file' => ['driver' => 'File', 'adapter' => 'Data', 'options' => ['cacheDir' => \BASE_PATH . '/storage/caches/']]]];
$parts = \parse_url(\DB_URL);
$infos = ['host' => $parts['host'], 'user' => $parts['user'], 'pass' => $parts['pass'], 'port' => $parts['port'], 'dbname' => \substr($parts['path'], 1)];
$config['database'] = ['default' => 'postgresql', 'connections' => ['postgresql' => ['adapter' => \Phalcon\Db\Adapter\Pdo\Postgresql::class, 'config' => ['host' => $infos['host'], 'username' => $infos['user'], 'password' => $infos['pass'], 'dbname' => $infos['dbname'], 'port' => $infos['port']]]]];
$config['error'] = ['formatter' => ['formatter' => \Phalcon\Logger\Formatter\Line::class, 'format' => '[%date%][%type%] %message%', 'dateFormat' => 'Y-m-d H:i:s O'], 'dispatcher' => ['namespace' => 'App\\Kernels\\Http\\Controllers', 'controller' => 'errors', 'action' => 'index'], 'view' => ['path' => 'errors', 'file' => 'http5xx']];
$config['log'] = ['adapter' => 'File', 'path' => \BASE_PATH . '/storage/logs/nucleon.log', 'options' => []];
$config['migrations'] = ['storage' => \Neutrino\Database\Migrations\Storage\DatabaseStorage::class, 'prefix' => \Neutrino\Database\Migrations\Prefix\DatePrefix::class, 'path' => \BASE_PATH . '/migrations'];
$config['session'] = ['adapter' => 'Files', 'id' => \SESSION_ID];
$config['view'] = ['views_dir' => \BASE_PATH . '/resources/views/', 'partials_dir' => \null, 'layouts_dir' => \null, 'compiled_path' => \BASE_PATH . '/storage/views/', 'implicit' => \false, 'engines' => ['.volt' => \Neutrino\View\Engines\Volt\VoltEngineRegister::class], 'extensions' => [\Neutrino\View\Engines\Volt\Compiler\Extensions\PhpFunctionExtension::class, \Neutrino\View\Engines\Volt\Compiler\Extensions\StrExtension::class], 'filters' => ['round' => \Neutrino\View\Engines\Volt\Compiler\Filters\RoundFilter::class, 'merge' => \Neutrino\View\Engines\Volt\Compiler\Filters\MergeFilter::class, 'split' => \Neutrino\View\Engines\Volt\Compiler\Filters\SplitFilter::class, 'slice' => \Neutrino\View\Engines\Volt\Compiler\Filters\SliceFilter::class]];
return $config;