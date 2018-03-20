<?php

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
                'host'     => DB_HOST,
                'username' => DB_USER,
                'password' => DB_PWD,
                'dbname'   => DB_NAME,
                'port'     => DB_PORT,
                'charset'  => 'utf8',
            ]
        ]
    ],
];