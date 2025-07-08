<?php
// Requisições dos helpers e DAOs necessários

require_once __DIR__ . '/../helpers/BitrixDealHelper.php';
require_once __DIR__ . '/../helpers/ClickSignHelper.php';
require_once __DIR__ . '/../helpers/LogHelper.php';
require_once __DIR__ . '/../dao/AplicacaoAcessoDAO.php';

use dao\AplicacaoAcessoDAO;

class ClickSignController
{
    public static function GerarAssinatura()
    {
        $params = $_GET;
        $cliente = $params['cliente'] ?? null;
        $entityId = $params['spa'] ?? $params['entityId'] ?? null;
        $id = $params['deal'] ?? $params['id'] ?? null;

        LogHelper::logClickSign("Início do método GerarAssinatura", 'controller');

        if (empty($cliente) || empty($id) || empty($entityId)) {
            LogHelper::logClickSign("Parâmetros obrigatórios ausentes.", 'controller');
            return ['success' => false, 'mensagem' => 'Parâmetros obrigatórios ausentes.'];
        }

        $acesso = AplicacaoAcessoDAO::obterWebhookPermitido($cliente, 'clicksign');
        if (!$acesso || empty($acesso['webhook_bitrix']) || empty($acesso['clicksign_token'])) {
            LogHelper::logClickSign("Acesso à aplicação ClickSign não autorizado ou incompleto.", 'controller');
            return ['success' => false, 'mensagem' => 'Acesso não autorizado ou incompleto.'];
        }

        $webhook = $acesso['webhook_bitrix'];
        $tokenClicksign = $acesso['clicksign_token'];

        LogHelper::logClickSign("Webhook obtido: {$webhook}", 'controller');
        LogHelper::logClickSign("Token obtido.", 'controller');

        $camposConsulta = [
            'contratante',
            'contratada',
            'testemunhas',
            'data',
            'arquivoaserassinado'
        ];

        $fields = [];
        foreach ($camposConsulta as $campo) {
            if (!empty($params[$campo])) {
                $fields[] = $params[$campo];
            }
        }

    $registro = BitrixDealHelper::consultarDeal($entityId, $id, $fields, $webhook);

    $dados = $registro['result']['item'] ?? [];

    // Extrai as chaves camelCase corretas para os campos
    $mapCampos = [];
    foreach ($camposConsulta as $campo) {
        if (!empty($params[$campo])) {
            $normalizado = BitrixHelper::formatarCampos([$params[$campo] => null]);
            $mapCampos[$campo] = array_key_first($normalizado);
        }
    }

    $idContratante = $dados[$mapCampos['contratante'] ?? ''] ?? null;
    $idContratada = $dados[$mapCampos['contratada'] ?? ''] ?? null;
    $idsTestemunhas = $dados[$mapCampos['testemunhas'] ?? ''] ?? null;
    $dataAssinatura = $dados[$mapCampos['data'] ?? ''] ?? null;

    // Novo bloco para garantir pegar só o urlMachine
    $campoArquivo = $dados[$mapCampos['arquivoaserassinado'] ?? ''] ?? null;
    $urlMachine = null;

    if (is_array($campoArquivo)) {
        // Se for múltiplo, pega o primeiro
        if (isset($campoArquivo[0]['urlMachine'])) {
            $urlMachine = $campoArquivo[0]['urlMachine'];
        } elseif (isset($campoArquivo['urlMachine'])) {
            $urlMachine = $campoArquivo['urlMachine'];
        }
    }

    // Monta array só com urlMachine
    $arquivoInfo = ['urlMachine' => $urlMachine];

   // Converte arquivo para base64
    $arquivoConvertido = BitrixDealHelper::baixarArquivoBase64($arquivoInfo);

    LogHelper::logClickSign('[DEBUG] Conteúdo base64: ' . substr($arquivoConvertido['base64'], 0, 60), 'ClickSignController');

        
    if (!$arquivoConvertido) {
        LogHelper::logClickSign("Falha ao processar o arquivo para base64.", 'controller');
        return ['success' => false, 'mensagem' => 'Erro ao converter o arquivo.'];
    }

    // Monta payload para ClickSign
    
        $payloadClickSign = [
            'document' => [
                'content_base64' => $arquivoConvertido['base64'],
                'name'           => $arquivoConvertido['nome'],           // era 'filename'
                'path'           => '/' . $arquivoConvertido['nome'],      // use o nome final com barra
                'content_type'   => $arquivoConvertido['mime'],            // adicione isso
            ]
        ];

    // Cria documento na ClickSign
    //LogHelper::logClickSign('[DEBUG] Payload enviado para ClickSign: ' . json_encode($payloadClickSign), 'ClickSignController');
    $retornoClickSign = ClickSignHelper::criarDocumento($payloadClickSign, $tokenClicksign);


    return $retornoClickSign;

    }
}
