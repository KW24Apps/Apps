<?php

require_once __DIR__ . '/../helpers/BitrixHelper.php';
require_once __DIR__ . '/../dao/AplicacaoAcessoDAO.php';
require_once __DIR__ . '/../helpers/BitrixDealHelper.php';
require_once __DIR__ . '/../helpers/BitrixDiskHelper.php';
require_once __DIR__ . '/../helpers/ClickSignHelper.php';

use dao\AplicacaoAcessoDAO;

class ClickSignController
{
    public function novo()
    {
        $dados = $_GET;
        $logPath = __DIR__ . '/../logs/clicksign.log';

        $acesso = AplicacaoAcessoDAO::obterWebhookPermitido($dados['cliente'] ?? '', 'clicksign');
        $webhookBitrix = trim($acesso['webhook_bitrix'] ?? '');
        $clicksignToken = $acesso['clicksign_token'] ?? null;
        $clicksignSecret = $acesso['clicksign_secret'] ?? null;

        if (!$webhookBitrix || !$clicksignToken || !$clicksignSecret) {
            http_response_code(403);
            echo json_encode(['erro' => 'Acesso negado ou credenciais incompletas.']);
            return;
        }

        $params = ['deal', 'spa', 'signatario', 'data', 'arquivoaserassinado', 'arquivoassinado', 'idclicksign', 'retorno'];
        foreach ($params as $param) {
            if (empty($dados[$param])) {
                http_response_code(400);
                echo json_encode(['erro' => 'Parâmetros obrigatórios ausentes.']);
                return;
            }
        }

        $negociacao = BitrixDealHelper::consultarNegociacao([
            'webhook' => $webhookBitrix,
            'deal' => $dados['deal'],
            'spa' => $dados['spa']
        ]);

        if (!isset($negociacao['result']['item'])) {
            http_response_code(404);
            echo json_encode(['erro' => 'Negociação não encontrada no Bitrix.']);
            return;
        }

        $item = $negociacao['result']['item'];
        $camposConvertidos = BitrixHelper::formatarCampos($item);
        $campoArquivo = BitrixHelper::formatarCampos([$dados['arquivoaserassinado'] => ''])[key($camposConvertidos)];

        $valorCampoArquivo = $camposConvertidos[$campoArquivo] ?? null;
        $fileId = is_array($valorCampoArquivo) && isset($valorCampoArquivo[0]['id']) ? $valorCampoArquivo[0]['id'] : null;

        if (!$fileId) {
            http_response_code(422);
            echo json_encode(['erro' => 'ID do arquivo não encontrado no campo especificado.']);
            return;
        }

        $linkArquivo = BitrixDiskHelper::obterLinkExterno($webhookBitrix, $fileId);

        if (!$linkArquivo) {
            http_response_code(500);
            echo json_encode(['erro' => 'Não foi possível obter link do arquivo.']);
            return;
        }

        $nomeDocumento = 'Assinatura - ' . ($item['TITLE'] ?? 'Documento');
        $respostaClicksign = ClickSignHelper::criarDocumento($clicksignToken, $nomeDocumento, $linkArquivo);

        echo json_encode([
            'status' => 'ok',
            'mensagem' => 'Documento enviado para ClickSign com sucesso.',
            'resposta' => $respostaClicksign
        ]);
    }
}