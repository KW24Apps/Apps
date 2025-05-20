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

// Webhook Bitrix24 para consulta
$webhook = 'https://gnapp.bitrix24.com.br/rest/21/32my76xfasvkj7ld/crm.item.get.json';

$postData = http_build_query([
    'entityTypeId' => $spa,
    'id' => $id
]);

$ch = curl_init($webhook);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
$result = curl_exec($ch);
curl_close($ch);

$response = json_decode($result, true);

// Verifica e filtra campos
if (isset($response['result']['item'])) {
    $item = $response['result']['item'];

    // Se o parâmetro "campos" foi informado, filtra
    if (isset($params['campos'])) {
        $camposDesejados = explode(',', $params['campos']);
        $dadosFiltrados = [];

        foreach ($camposDesejados as $campo) {
            $campoFormatado = lcfirst(str_replace('UF_CRM_', 'ufCrm', trim($campo)));
            $dadosFiltrados[$campo] = $item[$campoFormatado] ?? null;
        }

        echo json_encode([
            'status' => 'ok',
            'dados' => $dadosFiltrados
        ]);
    } else {
        // Retorna todos os dados
        echo json_encode([
            'status' => 'ok',
            'dados' => $item
        ]);
    }
} else {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Item não encontrado no Bitrix24.',
        'erro_bitrix' => $response['error_description'] ?? $response
    ]);
}
