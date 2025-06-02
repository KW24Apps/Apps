<?php

class PermissaoHelper
{
    public static function obterWebhookPermitido($clienteId, $tipo)
    {
        // Carrega configurações do banco de acordo com o ambiente
        require_once __DIR__ . '/../config/config.php';

        try {
            $pdo = new PDO("mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8", $config['usuario'], $config['senha']);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $stmt = $pdo->prepare("SELECT webhook_{$tipo} FROM clientes_api WHERE origem = :cliente LIMIT 1");
            $stmt->bindParam(':cliente', $clienteId);
            $stmt->execute();
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

            return $resultado ? trim($resultado["webhook_{$tipo}"]) : null;
        } catch (PDOException $e) {
            error_log("Erro DB: " . $e->getMessage());
            return null;
        }
    }
}
