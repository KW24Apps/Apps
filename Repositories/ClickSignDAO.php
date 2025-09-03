<?php
namespace Repositories;

require_once __DIR__ . '/../helpers/LogHelper.php';

use Helpers\LogHelper;
use PDO;
use PDOException;

class ClickSignDAO
{
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
                    (document_key, cliente_id, dados_conexao)
                    VALUES 
                    (:document_key, :cliente_id, :dados_conexao)";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($dados);
            return true; // Retorna sucesso
        } catch (PDOException $e) {
            // Log de erro PDO
            LogHelper::logClickSign("ERRO PDO ao inserir em assinaturas_clicksign: " . $e->getMessage(), 'dao');
            return false; // Retorna falha
        } catch (\Exception $e) {
            // Log de erro genérico
            LogHelper::logClickSign("ERRO geral ao salvar assinatura: " . $e->getMessage(), 'dao');
            return false; // Retorna falha
        }
    }

    // Método para salvar o status_closed de uma assinatura ClickSign
    public static function salvarStatus(string $documentKey, ?string $statusClosed = null, ?string $assinaturaProcessada = null, ?bool $documentoFechadoProcessado = null, ?bool $documentoDisponivelProcessado = null, ?bool $prazoAdiado = null): bool
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

            if ($prazoAdiado !== null) {
                $updates[] = 'prazo_adiado = :prazoAdiado';
                $params[':prazoAdiado'] = $prazoAdiado;
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

    // Método para obter todas as assinaturas ativas que precisam de verificação de prazo
    public static function obterAssinaturasAtivasParaVerificacao(): ?array
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
                SELECT document_key, dados_conexao, cliente_id, spa, deal_id, campo_data, campo_retorno
                FROM assinaturas_clicksign
                WHERE (documento_disponivel_processado = 0 OR documento_disponivel_processado IS NULL)
                AND (status_closed IS NULL OR status_closed NOT IN ('cancel', 'deadline'))
                AND (prazo_adiado = 0 OR prazo_adiado IS NULL)
                AND dados_conexao IS NOT NULL
            ";

            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            LogHelper::logClickSign("ERRO PDO ao obter assinaturas ativas para verificação: " . $e->getMessage(), 'dao');
            return null;
        }
    }
}
