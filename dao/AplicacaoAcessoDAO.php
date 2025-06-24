<?php

namespace dao;

use PDO;
use PDOException;

class AplicacaoAcessoDAO
{
    public static function obterWebhookPermitido($chaveAcesso, $slugAplicacao)
    {
        $config = require __DIR__ . '/../config/config.php';

        try {
            $pdo = new PDO(
                "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8",
                $config['usuario'],
                $config['senha']
            );
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $sql = "
                SELECT
                    ca.webhook_bitrix,
                    ca.clicksign_token,
                    ca.clicksign_secret
                FROM clientes c
                JOIN cliente_aplicacoes ca ON ca.cliente_id = c.id
                JOIN aplicacoes a ON ca.aplicacao_id = a.id
                WHERE c.chave_acesso = :chave
                  AND a.slug = :slug
                  AND ca.ativo = 1
                LIMIT 1
            ";

            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':chave', $chaveAcesso);
            $stmt->bindParam(':slug', $slugAplicacao);
            $stmt->execute();
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

            $log = [
                'dataHora' => date('Y-m-d H:i:s'),
                'chaveAcesso' => $chaveAcesso,
                'slugAplicacao' => $slugAplicacao,
                'sql' => trim($sql),
                'resultado' => $resultado
            ];
            file_put_contents(__DIR__ . '/../logs/aplicacao_acesso_debug.log', json_encode($log, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND);


            return $resultado ?: null;
        } catch (PDOException $e) {
            file_put_contents(__DIR__ . '/../logs/aplicacao_acesso_debug.log', 'Erro DB: ' . $e->getMessage() . PHP_EOL, FILE_APPEND);
            return null;
        }
    }
}
