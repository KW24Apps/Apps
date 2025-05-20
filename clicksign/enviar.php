<?php
file_put_contents(__DIR__ . '/log_enviar.txt', "[" . date('Y-m-d H:i:s') . "] INÍCIO ABSOLUTO DO ENVIAR.PHP\n", FILE_APPEND);

require_once __DIR__ . '/BitrixHelper.php';
require_once __DIR__ . '/Utils.php';

function log_enviar($mensagem) {
    $arquivo = __DIR__ . '/log_enviar.txt';
    $data = date('Y-m-d H:i:s');
    file_put_contents($arquivo, "[$data] $mensagem\n", FILE_APPEND);
}

try {
    log_enviar("Início do envio.");

    $params = $_GET;
    $idDeal = $params['iddeal'] ?? null;
    $spa = $params['spa'] ?? null;
    $campoArquivo = $params['arquivoaserassinado'] ?? null;
    $campoData = $params['data'] ?? null;

    if (!$idDeal || !$spa || !$campoArquivo || !$campoData) {
        throw new Exception("Parâmetros obrigatórios ausentes.");
    }

    log_enviar("Parâmetros recebidos: iddeal=$idDeal | spa=$spa | campoArquivo=$campoArquivo | campoData=$campoData");

    $bitrix = new BitrixHelper('https://gnapp.bitrix24.com.br/rest/21/348n8xqhp06wp41c/');
    $deal = $bitrix->getSpaItem($spa, $idDeal);
    if (!$deal || !isset($deal['result'])) {
        throw new Exception("Erro ao buscar item no Bitrix (SPA).");
    }

    $dadosDeal = $deal['result']['item'] ?? [];
    log_enviar("Dados do item recebidos com sucesso.");

    $campoFormatado = str_replace("UF_CRM_", "ufCrm", $campoArquivo);
    $arquivoArray = $dadosDeal[$campoFormatado] ?? [];

    if (is_array($arquivoArray) && count($arquivoArray) > 0 && isset($arquivoArray[0]["urlMachine"])) {
        $urlArquivo = $arquivoArray[0]["urlMachine"];
        log_enviar("URL do arquivo: $urlArquivo");
    } else {
        throw new Exception("Arquivo não encontrado no campo especificado.");
    }

    $resultadoDownload = Utils::downloadFileDetails(__DIR__ . '/tmp/', $urlArquivo);
    $caminhoCompleto = __DIR__ . '/tmp/' . $resultadoDownload["filename"];

    if (!file_exists($caminhoCompleto)) {
        throw new Exception("Falha no download do arquivo.");
    }

    log_enviar("Arquivo baixado com sucesso: " . $resultadoDownload["filename"]);
// Define nome final do documento
$nomeArquivoFinal = '';
if (!empty($dadosDeal['companyId'])) {
    log_enviar("Tentando buscar empresa vinculada...");
    try {
    $empresa = $bitrix->getCompany($dadosDeal['companyId']);
    log_enviar("Retorno bruto da empresa: " . json_encode($empresa));
} catch (Exception $e) {
    log_enviar("Erro ao buscar empresa: " . $e->getMessage());
}

    $nomeEmpresa = trim($empresa['result']['TITLE'] ?? '');
    $nomeArquivoFinal = '/' . preg_replace('/[^a-zA-Z0-9-_]/', '_', $nomeEmpresa) . "_{$idDeal}.pdf";
} else {
    $nomeOriginal = pathinfo($resultadoDownload["filename"], PATHINFO_FILENAME);
    $nomeArquivoFinal = '/' . preg_replace('/[^a-zA-Z0-9-_]/', '_', $nomeOriginal) . "_{$idDeal}.pdf";
}

log_enviar("Nome final gerado para o documento: " . $nomeArquivoFinal);


    // Passa nome final para o clicksign
    $_GET['nomefinal'] = $nomeArquivoFinal;

    $_GET['arquivo'] = $resultadoDownload["filename"];
    $_GET['data'] = $dadosDeal[str_replace("UF_CRM_", "ufCrm", $campoData)] ?? null;

    include __DIR__ . '/clicksign.php';

} catch (Exception $e) {
    log_enviar("Erro: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["erro" => $e->getMessage()]);
}
