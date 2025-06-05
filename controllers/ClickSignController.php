<?php

require_once __DIR__ . '/../helpers/BitrixHelper.php';
require_once __DIR__ . '/../dao/AplicacaoAcessoDAO.php';
require_once __DIR__ . '/../helpers/BitrixDealHelper.php';
require_once __DIR__ . '/../helpers/BitrixDiskHelper.php';
require_once __DIR__ . '/../helpers/ClickSignHelper.php';
require_once __DIR__ . '/../helpers/BitrixContactHelper.php';

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
        file_put_contents(__DIR__ . '/../logs/clicksign_dbinfo.log', "[DADOS BANCO] Acesso carregado: " . json_encode($acesso) . PHP_EOL, FILE_APPEND);

        $webhookBitrix = trim($acesso['webhook_bitrix'] ?? '');
        file_put_contents($logPath, "[DEBUG] Webhook usado: [" . $webhookBitrix . "]" . PHP_EOL, FILE_APPEND);

        $clicksignToken = $acesso['clicksign_token'] ?? null;
        $clicksignSecret = $acesso['clicksign_secret'] ?? null;

        if (!$webhookBitrix || !$clicksignToken || !$clicksignSecret) {
            http_response_code(403);
            $msg = 'Acesso negado ou credenciais ClickSign ausentes/incompletas.';
            file_put_contents($logPath, "[ERRO] $msg" . PHP_EOL, FILE_APPEND);
            echo json_encode(['erro' => $msg]);
            return;
        }

        $idDeal = $dados['deal'] ?? null;
        $spa = $dados['spa'] ?? null;
        $campoContratante = $dados['contratante'] ?? null;
        $campoContratada = $dados['contratada'] ?? null;
        $campoTestemunhas = $dados['testemunhas'] ?? null;
        $campoData = $dados['data'] ?? null;
        $campoArquivo = $dados['arquivoaserassinado'] ?? null;
        $campoArquivoFinal = $dados['arquivoassinado'] ?? null;
        $campoIdClicksign = $dados['idclicksign'] ?? null;
        $campoRetorno = $dados['retorno'] ?? null;

        if (!$idDeal || !$spa || !$campoData || !$campoArquivo || !$campoArquivoFinal || !$campoIdClicksign || !$campoRetorno || !($campoContratante || $campoContratada || $campoTestemunhas)) {
            http_response_code(400);
            $msg = 'Parâmetros obrigatórios ausentes.';
            file_put_contents($logPath, "[ERRO] $msg" . PHP_EOL, FILE_APPEND);
            echo json_encode(['erro' => $msg]);
            return;
        }

        function buscarContatos($campos, $webhook) {
            return BitrixContactHelper::consultarContatos($campos, $webhook, ['EMAIL']);
        }

        $signatarios = [];

        if ($campoContratante) {
            $contatoContratante = buscarContatos(['contratante' => $campoContratante], $webhookBitrix);
            if (!empty($contatoContratante)) {
                $signatarios[] = [
                    'email' => $contatoContratante[0]['EMAIL'],
                    'role' => 'contratante'
                ];
            }
        }

        if ($campoContratada) {
            $contatoContratada = buscarContatos(['contratada' => $campoContratada], $webhookBitrix);
            if (!empty($contatoContratada)) {
                $signatarios[] = [
                    'email' => $contatoContratada[0]['EMAIL'],
                    'role' => 'contratada'
                ];
            }
        }

        if ($campoTestemunhas) {
            $contatoTestemunha = buscarContatos(['testemunhas' => $campoTestemunhas], $webhookBitrix);
            if (!empty($contatoTestemunha)) {
                $signatarios[] = [
                    'email' => $contatoTestemunha[0]['EMAIL'],
                    'role' => 'testemunha'
                ];
            }
        }

        file_put_contents($logPath, "[OK] Validações concluídas com sucesso." . PHP_EOL, FILE_APPEND);

        $negociacao = BitrixDealHelper::consultarNegociacao([
            'webhook' => $webhookBitrix,
            'deal' => $idDeal,
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

        $camposConvertidos = BitrixHelper::formatarCampos([$campoArquivo => 1]);
        $chaveConvertida = array_key_first($camposConvertidos);
        $valorCampoArquivo = $item[$chaveConvertida] ?? null;

        file_put_contents($logPath, "[DEBUG] Conteúdo do campo arquivo [$chaveConvertida]: " . json_encode($valorCampoArquivo) . PHP_EOL, FILE_APPEND);

        $fileId = is_array($valorCampoArquivo) && isset($valorCampoArquivo[0]['id'])
            ? $valorCampoArquivo[0]['id']
            : null;

        if (!$fileId) {
            http_response_code(422);
            $msg = 'ID do arquivo não encontrado no campo especificado.';
            file_put_contents($logPath, "[ERRO] $msg" . PHP_EOL, FILE_APPEND);
            echo json_encode(['erro' => $msg]);
            return;
        }

        $arquivo = BitrixDiskHelper::extrairArquivoDoItem($item, $chaveConvertida);


        if (!$arquivo || empty($arquivo['urlMachine'])) {
            http_response_code(500);
            $msg = 'Link do arquivo ausente no retorno da API.';
            file_put_contents($logPath, "[ERRO] $msg" . PHP_EOL, FILE_APPEND);
            echo json_encode(['erro' => $msg]);
            return;
        }

        $conteudoArquivo = file_get_contents($arquivo['urlMachine']);

        $tmpPath = tempnam(sys_get_temp_dir(), 'mime');
        file_put_contents($tmpPath, $conteudoArquivo);
        $mime = mime_content_type($tmpPath);
        unlink($tmpPath);

        $extensoes = [
            'application/pdf' => 'pdf',
            'image/png' => 'png',
            'image/jpeg' => 'jpg'
        ];
        $ext = $extensoes[$mime] ?? 'bin';

        $nomeEmpresa = preg_replace('/[^\w\d_-]+/', '_', $item['TITLE'] ?? 'empresa_desconhecida');
        $nomeFinal = $nomeEmpresa . '_' . $idDeal . '.' . $ext;

        $base64 = "data:$mime;base64," . base64_encode($conteudoArquivo);
        $respostaClicksign = ClickSignHelper::criarDocumento($clicksignToken, $nomeFinal, $base64);

        file_put_contents($logPath, "[RESPOSTA CLICK] " . json_encode($respostaClicksign) . PHP_EOL, FILE_APPEND);

        echo json_encode([
            'status' => 'ok',
            'mensagem' => 'Documento enviado para ClickSign com sucesso.',
            'resposta' => $respostaClicksign
        ]);
    }
}
