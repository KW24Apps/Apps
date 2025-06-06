<?php

require_once __DIR__ . '/../helpers/BitrixHelper.php';
require_once __DIR__ . '/../helpers/BitrixDealHelper.php';
require_once __DIR__ . '/../helpers/BitrixCompanyHelper.php';
require_once __DIR__ . '/../helpers/BitrixContactHelper.php';
require_once __DIR__ . '/../helpers/BitrixDiskHelper.php';
require_once __DIR__ . '/../dao/AplicacaoAcessoDAO.php';

use dao\AplicacaoAcessoDAO;

class ClickSignController
{
        public function assinar()
        {
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
            
            // Imprimir os dados do acesso (webhook do Bitrix e token do ClickSign)
            echo json_encode([
                'webhook_bitrix' => $acesso['webhook_bitrix'],
                'clicksign_token' => $acesso['clicksign_token'],
                'cliente' => $cliente,
                'spa' => $spa,
                'deal' => $deal
            ]);

            // Receber os parâmetros dinamicamente da URL
            $camposDinamicos = [
                'contratante' => $_GET['contratante'] ?? null,
                'contratada' => $_GET['contratada'] ?? null,
                'testemunhas' => $_GET['testemunhas'] ?? null,
            ];

            // Consultar os dados do negócio (Deal)
            $filtros['webhook'] = $webhook;
            $filtros['deal'] = $deal;  // Deal ID (obtido da URL)
            $filtros['spa'] = $spa;  // SPA (obtido da URL)
            $resultado = BitrixDealHelper::consultarNegociacao($filtros);

            // Preparar a resposta com os dados filtrados dinamicamente
            $dadosFiltrados = [];

            // Adicionar o companyId (campo fixo) corretamente
            $dadosFiltrados['companyId'] = $resultado['result']['item']['companyId'] ?? null;

            // Agora, adicionar os campos dinâmicos
            foreach ($camposDinamicos as $campo => $campoId) {
                // Verifica se o campo está presente no resultado e adiciona ao array
                if ($campoId && isset($resultado['result']['item'][$campoId])) {
                    $dadosFiltrados[$campo] = $resultado['result']['item'][$campoId];
                }
            }

            // Obter o companyId diretamente da resposta do Deal
            $companyId = $resultado['result']['item']['companyId'] ?? null;

            // Verificar se o companyId foi encontrado
            if ($companyId) {
            // Consultar a empresa com o companyId do Deal
            $empresa = BitrixCompanyHelper::consultarEmpresa(['empresa' => $companyId, 'webhook' => $webhook]);

            // Verificar se a empresa foi encontrada e pegar o nome (title)
            $nomeEmpresa = isset($empresa['result']->TITLE) ? $empresa['result']->TITLE : 'Nome da empresa não encontrado';

            // Imprimir o nome da empresa
            echo json_encode([
                'nome_empresa' => $nomeEmpresa
            ]);

;
            } else {
                // Caso não tenha encontrado o companyId
                echo json_encode(['erro' => 'Company ID não encontrado no Deal.']);
            }


        }


}