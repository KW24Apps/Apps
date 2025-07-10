<?php
namespace dao;
use PDO;
use PDOException;

require_once __DIR__ . '/../helpers/LogHelper.php';

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
                    ca.clicksign_secret,
                    ca.cliente_id
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

    public static function registrarAssinaturaClicksign($dados)
    {
        $config = require __DIR__ . '/../config/config.php';

        try {
            $pdo = new PDO(
                "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8",
                $config['usuario'],
                $config['senha']
            );
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $sql = "INSERT INTO assinaturas_clicksign 
                    (document_key, cliente_id, deal_id, spa, campo_contratante, campo_contratada, campo_testemunhas, campo_data, campo_arquivoaserassinado, campo_arquivoassinado, campo_idclicksign, campo_retorno)
                    VALUES 
                    (:document_key, :cliente_id, :deal_id, :spa, :campo_contratante, :campo_contratada, :campo_testemunhas, :campo_data, :campo_arquivoaserassinado, :campo_arquivoassinado, :campo_idclicksign, :campo_retorno)";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($dados);
            } catch (\Exception $e) {
        }
    }



}
