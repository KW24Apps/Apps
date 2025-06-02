<?php

class PermissaoHelper
{
    public static function obterWebhookPermitido($clienteId, $tipo)
    {
        $config = require __DIR__ . '/../config/config.php';

        try {
            $pdo = new PDO(
                "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8",
                $config['usuario'],
                $config['senha']
            );
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $stmt = $pdo->prepare("SELECT webhook_{$tipo} FROM clientes_api WHERE origem = :cliente LIMIT 1");
            $stmt->bindParam(':cliente', $clienteId);
            $stmt->execute();
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($resultado && isset($resultado["webhook_{$tipo}"])) {
                return trim($resultado["webhook_{$tipo}"]);
            }

            // Log customizado para debug
            $log = [
                'clienteId' => $clienteId,
                'tipo' => $tipo,
                'resultado' => $resultado,
            ];
            file_put_contents(__DIR__ . '/../logs/permissao_debug.log', json_encode($log) . PHP_EOL, FILE_APPEND);

            return null;
        } catch (PDOException $e) {
            file_put_contents(__DIR__ . '/../logs/permissao_debug.log', 'Erro DB: ' . $e->getMessage() . PHP_EOL, FILE_APPEND);
            return null;
        }
    }
}
