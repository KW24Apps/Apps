<?php
// Aceita GET ou POST
$metodo = $_SERVER['REQUEST_METHOD'];

if ($metodo !== 'POST' && $metodo !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Método não permitido. Use GET ou POST.'
    ]);
    exit;
}

header('Content-Type: application/json');

$params = $metodo === 'POST' ? $_POST : $_GET;

// Verificações obrigatórias
if (!isset($params['spa']) || !isset($params['id'])) {
    http_response_code(400);
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Parâmetros obrigatórios "spa" e "id" não informados.'
    ]);
    exit;
}

$spa = $params['spa'];
$id = $params['id'];
unset($params['spa'], $params['id']);

$fields = [];

// Converte os campos UF_CRM para o padrão da API SPA
foreach ($params as $chave => $valor) {
    if (strpos($chave, 'UF_CRM') === 0) {
        $chaveConvertido = lcfirst(str_replace('UF_CRM_', 'ufCrm', $chave));
        $fields[$chaveConvertido] = $valor;
    }
}

// Webhook Bitrix24 com crm.item.update
$webhook = 'https://gnapp.bitrix24.com.br/rest/21/32my76xfasvkj7ld/crm.item.update.json';

$postData = http_build_query([
    'entityTypeId' => $spa,
    'id' => $id,
    'fields' => $fields
]);

$ch = curl_init($webhook);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
$result = curl_exec($ch);
curl_close($ch);

$response = json_decode($result, true);

// Retorno final
if (isset($response['result']) && $response['result'] === true) {
    echo json_encode([
        'status' => 'ok',
        'mensagem' => 'Card atualizado com sucesso!',
        'id_card' => $id,
        'campos_atualizados' => $fields
    ]);
} else {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro ao atualizar o card no Bitrix24.',
        'erro_bitrix' => $response['error_description'] ?? $response,
        'campos_enviados' => $fields
    ]);
}
