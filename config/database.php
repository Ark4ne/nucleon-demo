<?php

$parts = parse_url(DB_URL);

$infos = [
  'host' => $parts['host'],
  'user' => $parts['user'],
  'pass' => $parts['pass'],
  'port' => $parts['port'],
  'dbname' => substr($parts['path'], 1),
];
/*
 +-------------------------------------------------------------------
 | Database configuration
 +-------------------------------------------------------------------
 */
return [
    /*
     +---------------------------------------------------------------
     | Database adapter
     +---------------------------------------------------------------
     |
     | This value define the adapter to use
     | for connect to the database.
     |
     | Available adapter :
     |
     | \Phalcon\Db\Adapter\Pdo\Mysql
     | \Phalcon\Db\Adapter\Pdo\Postgresql
     | \Phalcon\Db\Adapter\Pdo\Sqlite
     |
     | (phalcon/incubator)
     | \Phalcon\Db\Adapter\Pdo\Oracle
     | \Phalcon\Db\Adapter\Mongo\Db
     */
    'default'     => 'postgresql',
    'connections' => [
        'postgresql' => [
            'adapter' => \Phalcon\Db\Adapter\Pdo\Postgresql::class,
            'config'  => [
                'host'     => $infos['host'],
                'username' => $infos['user'],
                'password' => $infos['pass'],
                'dbname'   => $infos['dbname'],
                'port'     => $infos['port'],
            ]
        ]
    ],
];