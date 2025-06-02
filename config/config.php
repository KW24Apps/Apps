<?php

// Ambiente de execução: 'local' ou 'producao'
$ambiente = 'local';

if ($ambiente === 'local') {
    $config = [
        'host' => 'localhost',
        'dbname' => 'bitrix_local', // ou APIs_kw24_local se tiver usado esse
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
