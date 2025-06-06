<?php

require_once __DIR__ . '/../helpers/BitrixHelper.php';
require_once __DIR__ . '/../helpers/BitrixDealHelper.php';
require_once __DIR__ . '/../helpers/BitrixCompanyHelper.php';
require_once __DIR__ . '/../helpers/BitrixContactHelper.php';
require_once __DIR__ . '/../helpers/BitrixDiskHelper.php';
require_once __DIR__ . '/../dao/AplicacaoAcessoDAO.php';

use dao\AplicacaoAcessoDAO;

class PrepararIntegracaoController
{
    public function executar()
    {
        $cliente = $_GET['cliente'] ?? null;
        $spa = $_GET['spa'] ?? null;
        $deal = $_GET['deal'] ?? null;

        if (!$cliente || !$spa || !$deal) {
            echo json_encode(['erro' => 'Parâmetros obrigatórios ausentes.']);
            return;
        }

        // Consultar dados de acesso (já coletado previamente)
        $acesso = AplicacaoAcessoDAO::obterWebhookPermitido($cliente, 'clicksign');
        if (!$acesso || empty($acesso['webhook_bitrix']) || empty($acesso['clicksign_token'])) {
            echo json_encode(['erro' => 'Acesso à aplicação ClickSign não autorizado ou incompleto.']);
            return;
        }

        // Reaproveitar dados para criar documento
        $webhook = $acesso['webhook_bitrix'];
        $tokenClicksign = $acesso['clicksign_token'];

        // Consultar o negócio sem filtro de campos
        $negociacao = BitrixDealHelper::consultarNegociacao([
            'spa' => $spa,
            'deal' => $deal,
            'webhook' => $webhook
        ]);

        if (isset($negociacao['erro'])) {
            echo json_encode(['erro' => 'Erro ao consultar o negócio.', 'detalhes' => $negociacao]);
            return;
        }

        $item = $negociacao['result']['item'] ?? [];

        // Consultar o arquivo usando o Disk Helper
        $arquivo = BitrixDiskHelper::extrairArquivoDoItem($item, 'ufCrm41_1727802593');
        if (!$arquivo || (empty($arquivo['urlMachine']) && empty($arquivo['url']))) {
            echo json_encode(['erro' => 'Arquivo não encontrado.']);
            return;
        }

        // Nome do arquivo
        $nomeArquivo = isset($arquivo['name']) ? $arquivo['name'] : basename(parse_url($arquivo['urlMachine'], PHP_URL_PATH));

        // Chamar o CriarDocumentoController e passar os dados necessários
        include_once __DIR__ . '/CriarDocumentoController.php';
        $criarDocumentoController = new CriarDocumentoController();

        // Passar os 4 parâmetros necessários para o método executar()
        $criarDocumentoController->executar($webhook, $tokenClicksign, $nomeArquivo, $arquivo['urlMachine']);
    }
}

// Execução do controlador
if (php_sapi_name() !== 'cli') {
    $controller = new PrepararIntegracaoController();
    $controller->executar();
}
