<?php

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
        error_log("Testando log sem caminho específico" . PHP_EOL);
        // Receber parâmetros da URL
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

        // Definir o webhook
        $webhook = $acesso['webhook_bitrix'];

        // Consultar os dados do negócio (Deal)
        $filtros['webhook'] = $webhook;
        $filtros['deal'] = $deal;  // Deal ID (obtido da URL)
        $filtros['spa'] = $spa;  // SPA (obtido da URL)
        $resultado = BitrixDealHelper::consultarNegociacao($filtros);

        // Preparar a resposta com os dados filtrados dinamicamente
        $dadosFiltrados = [];

        // Consultar a empresa com o companyId do Deal
        $companyId = $resultado['result']['item']['companyId'] ?? null;
        $empresa = BitrixCompanyHelper::consultarEmpresa(['empresa' => $companyId, 'webhook' => $webhook]);

        if ($empresa) {
            $dadosFiltrados['nome_empresa'] = $empresa['TITLE'] ?? 'Nome da empresa não encontrado';
        } else {
            $dadosFiltrados['nome_empresa'] = 'Nome da empresa não encontrado';
        }

        // Caminho do arquivo de log
        $logFile = 'X:/VSCode/apis.kw24.com.br/Apps/logs/consulta_clicksign.log';

        // Verificar o arquivo a ser assinado
        $campoArquivo = $_GET['arquivoaserassinado'] ?? 'ufCrm41_1727802593'; // Exemplo do campo para o arquivo
        $arquivo = BitrixDiskHelper::extrairArquivoDoItem($resultado['result']['item'], $campoArquivo);

        if ($arquivo) {
            // Usar o urlMachine para o arquivo
            $urlArquivo = $arquivo['urlMachine'] ?? null;

            // Log para verificar o valor da URL
            error_log("URL do arquivo (urlMachine): " . print_r($urlArquivo, true) . PHP_EOL, 3, $logFile);

            if ($urlArquivo) {
                // Renomear o arquivo com nome da empresa e ID do Deal
                $nomeArquivo = $dadosFiltrados['nome_empresa'] . '-' . $deal . '.' . pathinfo($arquivo['urlMachine'], PATHINFO_EXTENSION);

                // Log para verificar o nome do arquivo
                error_log("Nome do arquivo: " . $nomeArquivo . PHP_EOL, 3, $logFile);

                // Confirmar que o código está chegando aqui
                error_log("Chegamos ao ponto de enviar o arquivo para a ClickSign." . PHP_EOL, 3, $logFile);

                // Chamar ClickSignHelper para criar o documento
                $documento = ClickSignHelper::criarDocumento($acesso['clicksign_token'], $nomeArquivo, $urlArquivo);

                // Log para confirmar que o documento foi enviado
                error_log("Documento enviado para ClickSign: " . print_r($documento, true) . PHP_EOL, 3, $logFile);
            } else {
                echo json_encode(['erro' => 'URL do arquivo não encontrada ou inválida.']);
                return;
            }
        } else {
            echo json_encode(['erro' => 'Arquivo não encontrado ou URL inválida.']);
            return;
        }



        // Retornar a resposta
        echo json_encode($dadosFiltrados);
    }
}
