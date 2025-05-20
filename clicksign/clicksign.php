<?php
require_once __DIR__ . '/ClickSignHelper.php';

function log_clicksign($mensagem) {
    $arquivo = __DIR__ . '/log_clicksign.txt';
    $data = date('Y-m-d H:i:s');
    file_put_contents($arquivo, "[$data] $mensagem\n", FILE_APPEND);
}

try {
    log_clicksign("Início do envio com base no Click OK.");

    $params = $_GET;
    $arquivoNome = $_GET['arquivo'] ?? null;
    $dataLimite = $_GET['data'] ?? null;

    if (!$arquivoNome || !$dataLimite) {
        throw new Exception("Parâmetros obrigatórios ausentes.");
    }

    $caminhoArquivo = __DIR__ . '/tmp/' . $arquivoNome;
    if (!file_exists($caminhoArquivo)) {
        throw new Exception("Arquivo não encontrado.");
    }

    $conteudo = file_get_contents($caminhoArquivo);
    $mime = mime_content_type($caminhoArquivo);
    $base64 = base64_encode($conteudo);
    $contentBase64 = "data:{$mime};base64,{$base64}";

    $documento = [
        "path" => $_GET['nomefinal'] ?? ('/' . $arquivoNome),
        "content_base64" => $contentBase64,
        "mime_type" => $mime,
        "deadline_at" => $dataLimite,
        "auto_close" => true
    ];

    $token = '0d388bbc-640e-4f99-9d67-77513b41f36d';
    $url = "https://app.clicksign.com/api/v1/documents?access_token={$token}";

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(["document" => $documento]));

    $resposta = curl_exec($ch);
    $erro = curl_error($ch);
    curl_close($ch);

    if ($erro) {
        throw new Exception("Erro cURL: $erro");
    }

    log_clicksign("Resposta da ClickSign (OK): $resposta");

    echo $resposta;

} catch (Exception $e) {
    log_clicksign("Erro: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["erro" => $e->getMessage()]);
}
