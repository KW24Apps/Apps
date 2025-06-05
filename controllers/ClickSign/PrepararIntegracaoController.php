<?php
require_once __DIR__ . '/../../helpers/BitrixHelper.php';
require_once __DIR__ . '/../../helpers/BitrixDealHelper.php';
require_once __DIR__ . '/../../helpers/BitrixCompanyHelper.php';
require_once __DIR__ . '/../../helpers/BitrixContactHelper.php';
require_once __DIR__ . '/../../helpers/BitrixDiskHelper.php';
require_once __DIR__ . '/../../dao/AplicacaoAcessoDAO.php';

use dao\AplicacaoAcessoDAO;

class PrepararIntegracaoController
{
    public function executar()
    {
        $cliente = $_GET['cliente'] ?? null;
        $spa = $_GET['spa'] ?? null;
        $deal = $_GET['deal'] ?? null;

        $campoContratante = $_GET['contratante'] ?? null;
        $campoContratada = $_GET['contratada'] ?? null;
        $campoTestemunhas = $_GET['testemunhas'] ?? null;
        $campoData = $_GET['data'] ?? null;
        $campoArquivo = $_GET['arquivoaserassinado'] ?? null;

        if (!$cliente || !$spa || !$deal) {
            echo json_encode(['erro' => 'Parâmetros obrigatórios ausentes.']);
            return;
        }

        // Validação com banco de dados
        $acesso = AplicacaoAcessoDAO::obterWebhookPermitido($cliente, 'clicksign');
        if (!$acesso || empty($acesso['webhook_bitrix']) || empty($acesso['clicksign_token'])) {
            echo json_encode(['erro' => 'Acesso à aplicação ClickSign não autorizado ou incompleto.']);
            return;
        }

        $webhook = $acesso['webhook_bitrix'];

        $negociacao = BitrixDealHelper::consultarNegociacao([
            'spa' => $spa,
            'deal' => $deal,
            'webhook' => $webhook,
            'campos' => implode(',', [$campoContratante, $campoContratada, $campoTestemunhas, $campoData, $campoArquivo, 'COMPANY_ID'])
        ]);

        if (isset($negociacao['erro'])) {
            echo json_encode(['erro' => 'Erro ao consultar o negócio.', 'detalhes' => $negociacao]);
            return;
        }

        $item = $negociacao['result']['item'] ?? [];

        // Extrair arquivo a ser assinado
        $arquivo = BitrixDiskHelper::extrairArquivoDoItem($item, $campoArquivo);

        // Extrair empresa com debug
        $empresaId = $item['companyId'] ?? null;
        
        error_log("[DEBUG] companyId extraído: " . print_r($empresaId, true));
        $empresa = $empresaId ? BitrixCompanyHelper::consultarEmpresa(['empresa' => $empresaId, 'webhook' => $webhook]) : [];
        error_log("[DEBUG] retorno consultarEmpresa: " . print_r($empresa, true));

        // Extrair contatos
        $idsContatos = array_merge(
            (array)($item[$campoContratante] ?? []),
            (array)($item[$campoContratada] ?? []),
            (array)($item[$campoTestemunhas] ?? [])
        );

        $contatos = [];
        foreach ($idsContatos as $idContato) {
            $c = BitrixContactHelper::consultarContato(['contato' => $idContato, 'webhook' => $webhook]);
            if (!isset($c['erro'])) {
                $contatos[$idContato] = $c;
            }
        }

        $resumo = [
            'empresa' => $empresa,
            'contatos' => $contatos,
            'data_assinatura' => $item[$campoData] ?? null,
            'arquivo' => $arquivo,
            'negocio' => $item,
            'token_clicksign' => $acesso['clicksign_token'] ?? null,
            'secret_clicksign' => $acesso['clicksign_secret'] ?? null
        ];

        echo json_encode($resumo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}

if (php_sapi_name() !== 'cli') {
    $controller = new PrepararIntegracaoController();
    $controller->executar();
}
