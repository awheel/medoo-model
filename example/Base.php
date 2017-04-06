<?php

use awheel\MedooModel\MedooModel;

class Base extends MedooModel
{
    public function __construct()
    {
        $config = [
            'test'=> [
                'master'=> [
                    [
                        'database_type' => 'mysql',
                        'database_name' => 'test',
                        'prefix' => 'test_',
                        'server' => '127.0.0.1',
                        'port' => '3306',
                        'username'=>'root',
                        'password' => '123456',
                        'charset'=>'utf8mb4',
                    ]
                ],
                'slave'=> [
                    [
                        'database_type' => 'mysql',
                        'database_name' => 'test',
                        'prefix' => 'test_',
                        'server' => '127.0.0.1',
                        'port' => '3306',
                        'username'=>'root',
                        'password' => '123456',
                        'charset'=>'utf8mb4',
                    ]
                ]
            ]
        ];

        parent::__construct($config);
    }
}
