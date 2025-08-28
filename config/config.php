<?php

global $ambiente;

$config = [];

if ($ambiente === 0) { // 0 = Local
    $config = [
        'host' => 'localhost',
        'dbname' => 'apis_local',
        'usuario' => 'root',
        'senha' => ''
    ];
} else { // 1 = Produção
    $config = [
        'host' => 'localhost',
        'dbname' => 'kw24co49_api_kwconfig',
        'usuario' => 'kw24co49_kw24',
        'senha' => 'BlFOyf%X}#jXwrR-vi'
    ];
}

return $config;
