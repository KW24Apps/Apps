<?php
// WebhookHelper.php - Helper para buscar webhooks do banco de dados

class WebhookHelper 
{
    /**
     * Busca o webhook do Bitrix no banco de dados baseado no cliente e slug da aplicação
     */
    public function obterWebhookBitrix($chaveAcesso = null, $slugAplicacao = 'importar'): ?string
    {
        // Se não passou chave de acesso, tenta pegar dos parâmetros
        if (!$chaveAcesso) {
            $chaveAcesso = $_GET['cliente'] ?? null;
        }
        
        if (!$chaveAcesso) {
            return null;
        }

        // Carrega configurações de banco existentes do Apps
        $config = require __DIR__ . '/../../config/config.php';

        try {
            $pdo = new PDO(
                "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8",
                $config['usuario'],
                $config['senha']
            );
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $sql = "
                SELECT ca.webhook_bitrix
                FROM clientes c
                JOIN cliente_aplicacoes ca ON ca.cliente_id = c.id
                JOIN aplicacoes a ON ca.aplicacao_id = a.id
                WHERE c.chave_acesso = :chave
                AND a.slug = :slug
                AND ca.ativo = 1
                AND ca.webhook_bitrix IS NOT NULL
                AND ca.webhook_bitrix != ''
                LIMIT 1
            ";

            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':chave', $chaveAcesso);
            $stmt->bindParam(':slug', $slugAplicacao);
            $stmt->execute();
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

            return $resultado['webhook_bitrix'] ?? null;

        } catch (PDOException $e) {
            error_log("ERRO PDO ao obter webhook Bitrix: " . $e->getMessage());
            return null;
        } catch (Exception $e) {
            error_log("ERRO geral ao obter webhook Bitrix: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Verifica se o webhook está configurado corretamente
     */
    public static function validarWebhook($webhook): bool
    {
        return !empty($webhook) && 
               filter_var($webhook, FILTER_VALIDATE_URL) !== false &&
               strpos($webhook, 'bitrix24') !== false;
    }
}
