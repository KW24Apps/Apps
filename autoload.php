<?php
spl_autoload_register(function ($class) {
    $pastas = ['dao', 'helpers', 'controllers'];

    foreach ($pastas as $pasta) {
        $caminho = __DIR__ . '/' . $pasta . '/' . $class . '.php';
        if (file_exists($caminho)) {
            require_once $caminho;
            return;
        }
    }
});
