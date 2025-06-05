<?php

$ambiente = getenv('APP_ENV') ?: 'local';

$config = [];

if ($ambiente === 'local') {
    $config = [
        'host' => 'localhost',
        'dbname' => 'apis_local',
        'usuario' => 'root',
        'senha' => ''
    ];
} else {
    $config = [
        'host' => 'localhost',
        'dbname' => 'kw24co49_api_kwconfig',
        'usuario' => 'kw24co49_kw24',
        'senha' => 'BlFOyf%X}#jXwrR-vi'
    ];
}

return $config;
