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
        $chave = $dados['cliente'] ?? null;

        $logPath = __DIR__ . '/../logs/clicksign.log';
        file_put_contents($logPath, "[INICIO] Requisição recebida: " . json_encode($dados) . PHP_EOL, FILE_APPEND);

        $acesso = AplicacaoAcessoDAO::obterWebhookPermitido($chave, 'clicksign');

        $webhookBitrix = $acesso['webhook_bitrix'] ?? null;
        $clicksignToken = $acesso['clicksign_token'] ?? null;
        $clicksignSecret = $acesso['clicksign_secret'] ?? null;

        if (!$webhookBitrix) {
            http_response_code(403);
            $msg = 'Acesso negado: aplicação inativa ou chave inválida.';
            file_put_contents($logPath, "[ERRO] $msg" . PHP_EOL, FILE_APPEND);
            echo json_encode(['erro' => $msg]);
            return;
        }

        if (!$clicksignToken || !$clicksignSecret) {
            http_response_code(403);
            $msg = 'Credenciais ClickSign ausentes ou incompletas.';
            file_put_contents($logPath, "[ERRO] $msg" . PHP_EOL, FILE_APPEND);
            echo json_encode(['erro' => $msg]);
            return;
        }

        $idDeal = $dados['iddeal'] ?? null;
        $spa = $dados['spa'] ?? null;
        $campoSignatario = $dados['signatario'] ?? null;
        $campoData = $dados['data'] ?? null;
        $campoArquivo = $dados['arquivoaserassinado'] ?? null;
        $campoArquivoFinal = $dados['arquivoassinado'] ?? null;
        $campoIdClicksign = $dados['idclicksign'] ?? null;
        $campoRetorno = $dados['retorno'] ?? null;

        if (!$idDeal || !$spa || !$campoSignatario || !$campoData || !$campoArquivo || !$campoArquivoFinal || !$campoIdClicksign || !$campoRetorno) {
            http_response_code(400);
            $msg = 'Parâmetros obrigatórios ausentes.';
            file_put_contents($logPath, "[ERRO] $msg" . PHP_EOL, FILE_APPEND);
            echo json_encode(['erro' => $msg]);
            return;
        }

        file_put_contents($logPath, "[OK] Validações concluídas com sucesso." . PHP_EOL, FILE_APPEND);

        $negociacao = BitrixDealHelper::consultarNegociacao([
            'webhook' => $webhookBitrix,
            'id' => $idDeal,
            'spa' => $spa
        ]);

        file_put_contents($logPath, "[DEBUG] Dados do retorno Bitrix: " . json_encode($negociacao) . PHP_EOL, FILE_APPEND);


        if (!$negociacao || !isset($negociacao['result']['item'])) {
            http_response_code(404);
            $msg = 'Negociação não encontrada no Bitrix.';
            file_put_contents($logPath, "[ERRO] $msg" . PHP_EOL, FILE_APPEND);
            echo json_encode(['erro' => $msg]);
            return;
        }

        $item = $negociacao['result']['item'];
        $fileId = $item[$campoArquivo] ?? null;

        if (!$fileId) {
            http_response_code(422);
            $msg = 'ID do arquivo não encontrado no campo especificado.';
            file_put_contents($logPath, "[ERRO] $msg" . PHP_EOL, FILE_APPEND);
            echo json_encode(['erro' => $msg]);
            return;
        }

        $linkArquivo = BitrixDiskHelper::obterLinkExterno($webhookBitrix, $fileId);

        if (!$linkArquivo) {
            http_response_code(500);
            $msg = 'Não foi possível obter link do arquivo.';
            file_put_contents($logPath, "[ERRO] $msg" . PHP_EOL, FILE_APPEND);
            echo json_encode(['erro' => $msg]);
            return;
        }

        file_put_contents($logPath, "[OK] Link do arquivo obtido: $linkArquivo" . PHP_EOL, FILE_APPEND);

        $nomeDocumento = 'Assinatura - ' . ($item['TITLE'] ?? 'Documento');
        $respostaClicksign = ClickSignHelper::criarDocumento($clicksignToken, $nomeDocumento, $linkArquivo);

        file_put_contents($logPath, "[RESPOSTA CLICK] " . json_encode($respostaClicksign) . PHP_EOL, FILE_APPEND);

        echo json_encode([
            'status' => 'ok',
            'mensagem' => 'Documento enviado para ClickSign com sucesso.',
            'resposta' => $respostaClicksign
        ]);
    }
}
