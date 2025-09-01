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
        LogHelper::logAcessoAplicacao(['mensagem' => 'DEBUG: Entrou em AplicacaoAcessoDAO::ValidarClienteAplicacao', 'slug' => $slugAplicacao], 'DEBUG');
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


}
