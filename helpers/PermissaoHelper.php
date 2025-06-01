<?php

class PermissaoHelper
{
    public static function obterWebhookPermitido($clienteId, $tipo)
    {
        $host = 'localhost';
        $dbname = 'kw24co49_api_kwconfig';
        $usuario = 'kw24co49_kw24';
        $senha = 'BlFOyf%X}#jXwrR-vi';

        try {
            $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $usuario, $senha);
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
