<?php
header("Content-Type: application/json");

$method = $_SERVER["REQUEST_METHOD"];
$id = $_GET["id"] ?? null;
$rawValor = $_GET["valor"] ?? null;

if ($method !== "POST" && $method !== "GET") {
    http_response_code(405);
    echo json_encode(["erro" => "Método não permitido. Use POST ou GET."]);
    exit;
}

if (!$id) {
    http_response_code(400);
    echo json_encode(["erro" => "Parâmetro 'id' ausente na URL"]);
    exit;
}
if (!$rawValor) {
    http_response_code(400);
    echo json_encode(["erro" => "Parâmetro 'valor' ausente na URL"]);
    exit;
}

$valor = floatval($rawValor);
if (!is_numeric($valor)) {
    http_response_code(400);
    echo json_encode(["erro" => "Valor monetário inválido"]);
    exit;
}

$API_CONVERSAO = "https://apis.kw24.com.br/app/ExtensoApi/convert.php";
$BITRIX_WEBHOOK_URL = "https://gnapp.bitrix24.com.br/rest/4743/4z62gw2qawdwm5ha/";
$CAMPO_VALOR_EXTENSO = "UF_CRM_1742475560";

// Faz requisição para o endpoint de conversão
$ch = curl_init($API_CONVERSAO);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(["valor" => $valor]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
$response = curl_exec($ch);
curl_close($ch);

$conversao = json_decode($response, true);
$valorExtenso = $conversao["porExtenso"] ?? null;

if (!$valorExtenso) {
    http_response_code(500);
    echo json_encode(["erro" => "Erro na conversão para extenso"]);
    exit;
}

// Atualiza o negócio no Bitrix24
$ch = curl_init($BITRIX_WEBHOOK_URL . "crm.deal.update");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    "id" => $id,
    "fields" => [
        $CAMPO_VALOR_EXTENSO => $valorExtenso
    ]
]));
$responseBitrix = curl_exec($ch);
curl_close($ch);

echo json_encode([
    "mensagem" => "✅ Valor convertido e atualizado com sucesso!",
    "id" => $id,
    "valor" => $valor,
    "valorExtenso" => $valorExtenso
]);

