<?php
$router = \Phalcon\Di::getDefault()->getShared('router');
$router->setDefaultNamespace('App\\Kernels\\Http\\Controllers')->notFound(['controller' => 'errors', 'action' => 'http404']);
$router->add('/', ['controller' => 'home', 'action' => 'index'], 'GET');
$router->add('/index', ['namespace' => 'App\\Kernels\\Http\\Modules\\Frontend\\Controllers', 'module' => 'Frontend', 'controller' => 'index', 'action' => 'index'], 'GET');
$router->add('/register', ['namespace' => 'App\\Kernels\\Http\\Modules\\Frontend\\Controllers', 'module' => 'Frontend', 'controller' => 'auth', 'action' => 'register'], 'GET');
$router->add('/register', ['namespace' => 'App\\Kernels\\Http\\Modules\\Frontend\\Controllers', 'module' => 'Frontend', 'controller' => 'auth', 'action' => 'postRegister'], 'POST');
$router->add('/login', ['namespace' => 'App\\Kernels\\Http\\Modules\\Frontend\\Controllers', 'module' => 'Frontend', 'controller' => 'auth', 'action' => 'login'], 'GET');
$router->add('/login', ['namespace' => 'App\\Kernels\\Http\\Modules\\Frontend\\Controllers', 'module' => 'Frontend', 'controller' => 'auth', 'action' => 'postLogin'], 'POST');
$router->add('/logout', ['namespace' => 'App\\Kernels\\Http\\Modules\\Frontend\\Controllers', 'module' => 'Frontend', 'controller' => 'auth', 'action' => 'logout'], 'GET');
$router->add('/example/api/index', ['namespace' => 'App\\Kernels\\Http\\Modules\\Example\\Controllers', 'module' => 'Example', 'controller' => 'Api', 'action' => 'index'], 'GET');
$router->add('/example/debug/exception', ['namespace' => 'App\\Kernels\\Http\\Modules\\Example\\Controllers', 'module' => 'Example', 'controller' => 'debug', 'action' => 'exception'], 'GET');
$router->add('/example/debug/throw-exception', ['namespace' => 'App\\Kernels\\Http\\Modules\\Example\\Controllers', 'module' => 'Example', 'controller' => 'debug', 'action' => 'throwException'], 'GET');
$router->add('/example/debug/var-dump', ['namespace' => 'App\\Kernels\\Http\\Modules\\Example\\Controllers', 'module' => 'Example', 'controller' => 'debug', 'action' => 'varDump'], 'GET');
$router->add('/back/:controller/:action', ['namespace' => 'App\\Kernels\\Http\\Modules\\Backend\\Controllers', 'module' => 'Backend'], 'GET');