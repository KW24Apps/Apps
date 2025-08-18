<?php
// Configuração do banco para o sistema de formulários
function getDbConnection() {
    try {
        $pdo = new PDO(
            'mysql:host=localhost;dbname=kw24co49_api_kwconfig;charset=utf8mb4',
            'kw24co49_kw24',
            'BlFOyf%X}#jXwrR-vi'
        );
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        error_log("Erro conexão DB: " . $e->getMessage());
        throw $e;
    }
}
