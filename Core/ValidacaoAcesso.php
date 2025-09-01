<?php

namespace Core;

require_once __DIR__ . '/../Repositories/AplicacaoAcessoDAO.php';
require_once __DIR__ . '/../helpers/LogHelper.php';

use Repositories\AplicacaoAcessoDAO;
use Helpers\LogHelper;

class ValidacaoAcesso
{
    /**
     * Executa a validação de autenticação.
     * Verifica se a chave do cliente foi fornecida e se ela tem permissão
     * para acessar a aplicação (slug).
     * @param string|null $slug O slug da aplicação (prefixo da rota).
     * @return bool Retorna true se a autenticação for bem-sucedida, false caso contrário.
     */
    public static function handle($cliente, $slug)
    {
        LogHelper::logAcessoAplicacao(['mensagem' => 'DEBUG: Entrou em ValidacaoAcesso::handle', 'slug' => $slug, 'cliente' => $cliente], 'DEBUG');

        if ($slug && $slug !== 'bitrix-sync') {
            $acesso = AplicacaoAcessoDAO::ValidarClienteAplicacao($cliente, $slug);
            if (!$acesso) {
                http_response_code(401);
                echo json_encode([
                    'success' => false,
                    'message' => 'Acesso negado. Verifique a chave do cliente e as permissões da aplicação.'
                ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                return false; // Interrompe a execução
            }
        }

        return true; // Permite a execução
    }
}
