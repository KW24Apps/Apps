<?php

require_once __DIR__ . '/../helpers/BitrixHelper.php';
require_once __DIR__ . '/../dao/AplicacaoAcessoDAO.php';
require_once __DIR__ . '/../helpers/BitrixDealHelper.php';

use dao\AplicacaoAcessoDAO;

class ClickSignController
{
    public function novo()
    {
        $dados = $_GET;
        $chave = $dados['cliente'] ?? null;

        $logPath = __DIR__ . '/../logs/clicksign.log';
        file_put_contents($logPath, "[INICIO] Requisição recebida: " . json_encode($dados) . PHP_EOL, FILE_APPEND);

        // Valida chave e aplicação via slug 'clicksign'
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

        // Consulta o Deal no Bitrix
        $negociacao = BitrixDealHelper::consultarNegociacao([
            'webhook' => $webhookBitrix,
            'id' => $idDeal,
            'spa' => $spa
        ]);

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

        file_put_contents($logPath, "[OK] Negociação localizada e arquivo ID obtido: $fileId" . PHP_EOL, FILE_APPEND);

        // Placeholder para busca do arquivo
        echo json_encode([
            'status' => 'ok',
            'mensagem' => 'Negociação localizada e arquivo identificado.',
            'fileId' => $fileId,
            'clicksign_token' => $clicksignToken,
            'clicksign_secret' => $clicksignSecret
        ]);
    }
}
