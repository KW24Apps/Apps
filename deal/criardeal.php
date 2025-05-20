<?php

// Permite GET e POST
$metodo = $_SERVER['REQUEST_METHOD'];

// Bloqueia outros métodos
if ($metodo !== 'POST' && $metodo !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Método não permitido. Use GET ou POST.'
    ]);
    exit;
}

// Define retorno em JSON
header('Content-Type: application/json');

// Lê os parâmetros conforme o método da requisição
$params = $metodo === 'POST' ? $_POST : $_GET;

// Verifica se o parâmetro 'spa' foi informado
if (!isset($params['spa'])) {
    http_response_code(400);
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Parâmetro obrigatório "spa" não informado.'
    ]);
    exit;
}

// Captura e remove o spa (entityTypeId)
$spa = $params['spa'];
unset($params['spa']);

// Inicia array de campos
$fields = [];

// Adiciona CATEGORY_ID (funil) se informado
if (isset($params['CATEGORY_ID'])) {
    $fields['categoryId'] = $params['CATEGORY_ID'];
    unset($params['CATEGORY_ID']);
}

// Converte campos UF_CRM_... para o padrão correto da API SPA (ufCrm...)
foreach ($params as $chave => $valor) {
    if (strpos($chave, 'UF_CRM') === 0) {
        $chaveConvertido = lcfirst(str_replace('UF_CRM_', 'ufCrm', $chave));
        $fields[$chaveConvertido] = $valor;
    }
}

// Webhook real do Bitrix24 com método crm.item.add
$webhook = 'https://gnapp.bitrix24.com.br/rest/21/32my76xfasvkj7ld/crm.item.add.json';

// Prepara os dados para envio via POST
$postData = http_build_query([
    'entityTypeId' => $spa,
    'fields' => $fields
]);

// Envia requisição CURL para o Bitrix
$ch = curl_init($webhook);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
$result = curl_exec($ch);
curl_close($ch);

// Interpreta resposta do Bitrix
$response = json_decode($result, true);

// Retorna sucesso ou erro
if (isset($response['result']['item']['id'])) {
    echo json_encode([
        'status' => 'ok',
        'mensagem' => 'Negócio criado com sucesso via SPA!',
        'id_card' => $response['result']['item']['id'],
        'campos_enviados' => $fields
    ]);
} else {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro ao criar item no Bitrix24.',
        'erro_bitrix' => $response['error_description'] ?? $response,
        'campos_enviados' => $fields
    ]);
}
