<?php

require_once __DIR__ . '/../../dao/AplicacaoAcessoDAO.php';
require_once __DIR__ . '/../../helpers/BitrixHelper.php';
require_once __DIR__ . '/../../helpers/BitrixDiskHelper.php';
require_once __DIR__ . '/../../helpers/ClickSignHelper.php';

class CriarDocumentoController
{
    public function executar($webhook, $tokenClicksign, $nomeArquivo, $urlArquivo)
    {
        // Verificar se o tokenClicksign está correto
        if (empty($tokenClicksign)) {
            echo json_encode(['erro' => 'Token ClickSign está vazio.']);
            return;
        }

        // Verificar o nome do arquivo
        if (empty($nomeArquivo)) {
            echo json_encode(['erro' => 'Nome do arquivo não foi gerado corretamente.']);
            return;
        }

        // Verificar a URL do arquivo
        if (empty($urlArquivo)) {
            echo json_encode(['erro' => 'URL do arquivo não encontrada.']);
            return;
        }

        // Criar documento na ClickSign
        $resposta = ClickSignHelper::criarDocumento($tokenClicksign, $nomeArquivo, $urlArquivo);

        // Exibir resposta da ClickSign
        echo json_encode($resposta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}

// Execução do controlador
if (php_sapi_name() !== 'cli') {
    $controller = new CriarDocumentoController();
    $controller->executar();
}
