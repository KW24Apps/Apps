<?php
namespace Repositories;

require_once __DIR__ . '/../helpers/LogHelper.php';

use Helpers\LogHelper;
use PDO;
use PDOException;



class AplicacaoAcessoDAO
{
    
    // Método para obter o acesso de uma aplicação com base na chave de acesso e no slug da aplicação
    public static function ValidarClienteAplicacao($chaveAcesso, $slugAplicacao)
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
                SELECT ca.*, c.nome as cliente_nome
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

            // Salva o resultado global para acesso futuro (boa prática para facilitar no controller/helper)
            if ($resultado) {
                $GLOBALS['ACESSO_AUTENTICADO'] = $resultado;
            }

            // Log apenas informações mínimas e seguras
            $nomeCliente = $resultado['cliente_nome'] ?? 'desconhecido';
            LogHelper::logAcessoAplicacao(['mensagem' => 'Acesso liberado', 'cliente' => $nomeCliente, 'slug' => $slugAplicacao, 'status' => $resultado ? 'ok' : 'falha'], __CLASS__ . '::' . __FUNCTION__);
            return $resultado ?: null;
        } catch (PDOException $e) {
            LogHelper::logAcessoAplicacao(['mensagem' => 'Erro DB', 'erro' => $e->getMessage()], __CLASS__ . '::' . __FUNCTION__);
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
                    (document_key, cliente_id, deal_id, spa, Signatarios, campo_contratante, campo_contratada, campo_testemunhas, campo_data, campo_arquivoaserassinado, campo_arquivoassinado, campo_idclicksign, campo_retorno, etapa_concluida)
                    VALUES 
                    (:document_key, :cliente_id, :deal_id, :spa, :Signatarios, :campo_contratante, :campo_contratada, :campo_testemunhas, :campo_data, :campo_arquivoaserassinado, :campo_arquivoassinado, :campo_idclicksign, :campo_retorno, :etapa_concluida)";
            
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

    // Método para obter todas as configurações de clientes com integração ClickSign ativa
    public static function obterConfiguracoesClickSignAtivas(): ?array
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
                SELECT ca.*, c.nome as cliente_nome
                FROM clientes c
                JOIN cliente_aplicacoes ca ON ca.cliente_id = c.id
                JOIN aplicacoes a ON ca.aplicacao_id = a.id
                WHERE a.slug = 'clicksign'
                AND ca.ativo = 1
                AND ca.config_extra IS NOT NULL
                AND JSON_UNQUOTE(JSON_EXTRACT(ca.config_extra, '$.\"SPA_1\".clicksign_token')) IS NOT NULL
                AND JSON_UNQUOTE(JSON_EXTRACT(ca.config_extra, '$.\"SPA_1\".clicksign_token')) <> ''
            ";

            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            LogHelper::logClickSign("ERRO PDO ao obter configurações ativas: " . $e->getMessage(), 'dao');
            return null;
        }
    }

    // Método para obter a configuração de uma aplicação específica para um cliente
    public static function obterConfiguracaoPorClienteId(int $clienteId, string $slugAplicacao): ?array
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
                SELECT ca.*, c.nome as cliente_nome
                FROM clientes c
                JOIN cliente_aplicacoes ca ON ca.cliente_id = c.id
                JOIN aplicacoes a ON ca.aplicacao_id = a.id
                WHERE ca.cliente_id = :clienteId
                AND a.slug = :slug
                AND ca.ativo = 1
                LIMIT 1
            ";

            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':clienteId', $clienteId, PDO::PARAM_INT);
            $stmt->bindParam(':slug', $slugAplicacao);
            $stmt->execute();
            
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        } catch (PDOException $e) {
            LogHelper::logClickSign("ERRO PDO ao obter configuração por cliente ID: " . $e->getMessage(), 'dao');
            return null;
        }
    }
}
