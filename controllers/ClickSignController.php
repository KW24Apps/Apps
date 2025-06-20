<?php

// Requisições dos helpers e DAOs necessários
require_once __DIR__ . '/../helpers/BitrixHelper.php';
require_once __DIR__ . '/../helpers/BitrixDealHelper.php';
require_once __DIR__ . '/../helpers/BitrixCompanyHelper.php';
require_once __DIR__ . '/../helpers/BitrixDiskHelper.php';
require_once __DIR__ . '/../helpers/ClickSignHelper.php';
require_once __DIR__ . '/../dao/AplicacaoAcessoDAO.php';

use dao\AplicacaoAcessoDAO;
class ClickSignController
{
    public function assinar()
    {
        // Caminho para o log de depuração
        $logFile = 'X:/VSCode/apis.kw24.com.br/Apps/logs/debug_clicksign.log';

        // Log de entrada no controlador
        error_log("Início da execução do ClickSignController - Função Assinar" . PHP_EOL, 3, $logFile);

        // Inicializar a variável $dadosFiltrados
        $dadosFiltrados = [];

        // Receber parâmetros da URL
        $cliente = $_GET['cliente'] ?? null;
        $spa = $_GET['spa'] ?? null;
        $deal = $_GET['deal'] ?? null;

        error_log("Parâmetros recebidos - Cliente: $cliente, SPA: $spa, Deal: $deal" . PHP_EOL, 3, $logFile);

        if (!$cliente || !$spa || !$deal) {
            echo json_encode(['erro' => 'Parâmetros obrigatórios ausentes.']);
            return;
        }

        // Consultar dados de acesso
        $acesso = AplicacaoAcessoDAO::obterWebhookPermitido($cliente, 'clicksign');
        if (!$acesso || empty($acesso['webhook_bitrix']) || empty($acesso['clicksign_token'])) {
            echo json_encode(['erro' => 'Acesso à aplicação ClickSign não autorizado ou incompleto.']);
            return;
        }

        // Definir o webhook
        $webhook = $acesso['webhook_bitrix'];
        $tokenClicksign = $acesso['clicksign_token'];

        error_log("Webhook e Token ClickSign recebidos." . PHP_EOL, 3, $logFile);

        // Consultar os dados do negócio (Deal)
        $filtros['webhook'] = $webhook;
        $filtros['deal'] = $deal;  // Deal ID (obtido da URL)
        $filtros['spa'] = $spa;  // SPA (obtido da URL)
        $resultado = BitrixDealHelper::consultarNegociacao($filtros);

        // Log para verificar o resultado completo do Deal
        error_log("Resultado completo do Deal: " . print_r($resultado, true) . PHP_EOL, 3, $logFile);

        // Consultar a empresa com o companyId do Deal
        $companyId = $resultado['result']['item']['companyId'] ?? null;
        $empresa = BitrixCompanyHelper::consultarEmpresa(['empresa' => $companyId, 'webhook' => $webhook]);

        // Log para o retorno completo da empresa
        error_log("Resultado completo do CRM Company: " . print_r($empresa, true) . PHP_EOL, 3, $logFile);

        // Adicionar a empresa ao array
        $dadosFiltrados['nome_empresa'] = $empresa['TITLE'] ?? 'Nome da empresa não encontrado';

        // Verificar o arquivo a ser assinado
        $campoArquivo = $_GET['arquivoaserassinado'] ?? 'ufCrm41_1727802593'; // Exemplo do campo para o arquivo
        $arquivos = $resultado['result']['item'][$campoArquivo] ?? [];

        // Log para verificar os arquivos encontrados
        error_log("Arquivos encontrados: " . print_r($arquivos, true) . PHP_EOL, 3, $logFile);

        // Verificar se algum arquivo foi encontrado e processar
        $urlArquivo = null;
        foreach ($arquivos as $arquivo) {
            if (isset($arquivo['urlMachine'])) {
                $urlArquivo = $arquivo['urlMachine'];
                break;
            }
        }

        // Log para verificar a URL do arquivo
        error_log("URL do arquivo (urlMachine): " . print_r($urlArquivo, true) . PHP_EOL, 3, $logFile);

        if ($urlArquivo) {
            // Renomear o arquivo com nome da empresa e ID do Deal
            $nomeArquivo = $dadosFiltrados['nome_empresa'] . '-' . $deal . '.' . pathinfo($urlArquivo, PATHINFO_EXTENSION);

            // Log para verificar o nome do arquivo
            error_log("Nome do arquivo: " . $nomeArquivo . PHP_EOL, 3, $logFile);

            // Chamar ClickSignHelper para criar o documento
            $documento = ClickSignHelper::criarDocumento($tokenClicksign, $nomeArquivo, $urlArquivo);

            // Log para confirmar que o documento foi enviado
            error_log("Documento enviado para ClickSign: " . print_r($documento, true) . PHP_EOL, 3, $logFile);
        } else {
            echo json_encode(['erro' => 'URL do arquivo não encontrada ou inválida.']);
            return;
        }

        // Retornar a resposta
        echo json_encode($dadosFiltrados);
    }
}
