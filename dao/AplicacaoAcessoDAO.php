<?php
namespace dao;
use LogHelper;
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

    // Método para registrar uma assinatura ClickSign no banco de dados
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
        } catch (PDOException $e) {
            // Log de erro PDO
            LogHelper::logClickSign("ERRO PDO ao inserir em assinaturas_clicksign: " . $e->getMessage(), 'dao');
        } catch (\Exception $e) {
            // Log de erro genérico
            LogHelper::logClickSign("ERRO geral ao salvar assinatura: " . $e->getMessage(), 'dao');
        }
    }

    // Método para salvar o status_closed de uma assinatura ClickSign
    public static function salvarStatus(string $documentKey, ?string $statusClosed = null, ?string $assinaturaProcessada = null, ?bool $documentoFechadoProcessado = null, ?bool $documentoDisponivelProcessado = null): bool
    {
        $config = require __DIR__ . '/../config/config.php';

        try {
            $pdo = new PDO(
                "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8",
                $config['usuario'],
                $config['senha']
            );
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $updates = [];
            $params = [':documentKey' => $documentKey];

            if ($statusClosed !== null) {
                $updates[] = 'status_closed = :statusClosed';
                $params[':statusClosed'] = $statusClosed;
            }

            if ($assinaturaProcessada !== null) {
                $updates[] = 'assinatura_processada = :assinaturaProcessada';
                $params[':assinaturaProcessada'] = $assinaturaProcessada;
            }

            if ($documentoFechadoProcessado !== null) {
                $updates[] = 'documento_fechado_processado = :docFechadoProc';
                $params[':docFechadoProc'] = $documentoFechadoProcessado;
            }

            if ($documentoDisponivelProcessado !== null) {
                $updates[] = 'documento_disponivel_processado = :docDisponivelProc';
                $params[':docDisponivelProc'] = $documentoDisponivelProcessado;
            }

            if (empty($updates)) {
                LogHelper::logClickSign("Nenhum campo informado para atualizar", 'dao');
                return false;
            }

            $sql = "UPDATE assinaturas_clicksign SET " . implode(', ', $updates) . " WHERE document_key = :documentKey";

            $stmt = $pdo->prepare($sql);
            return $stmt->execute($params);

        } catch (PDOException $e) {
            LogHelper::logClickSign("ERRO PDO ao atualizar status_closed: " . $e->getMessage(), 'dao');
            return false;
        } catch (\Exception $e) {
            LogHelper::logClickSign("ERRO geral ao atualizar status_closed: " . $e->getMessage(), 'dao');
            return false;
        }
    }

    // Método para obter uma assinatura ClickSign completa pelo documentKey
    public static function obterAssinaturaClickSign(string $documentKey): ?array
    {
        $config = require __DIR__ . '/../config/config.php';

        try {
            $pdo = new PDO(
                "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8",
                $config['usuario'],
                $config['senha']
            );
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $sql = "SELECT * FROM assinaturas_clicksign WHERE document_key = :documentKey LIMIT 1";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':documentKey', $documentKey);
            $stmt->execute();

            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            return $resultado ?: null;

        } catch (PDOException $e) {
            LogHelper::logClickSign("ERRO PDO ao obter assinatura completa: " . $e->getMessage(), 'dao');
            return null;
        } catch (\Exception $e) {
            LogHelper::logClickSign("ERRO geral ao obter assinatura completa: " . $e->getMessage(), 'dao');
            return null;
        }
    }

}
