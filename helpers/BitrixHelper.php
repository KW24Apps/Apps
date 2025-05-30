<?php

// Função para formatar os campos conforme esperado pelo Bitrix (SPA)
function formatarCampos($dados)
{
    $fields = [];

    foreach ($dados as $campo => $valor) {
        if (strpos($campo, 'UF_CRM_') === 0) {
            // Transforma UF_CRM_123_ABC em ufCrm123ABC
            $chaveConvertida = lcfirst(str_replace('_', '', str_replace('UF_CRM_', 'ufCrm_', $campo)));
            $fields[$chaveConvertida] = $valor;
        } else {
            $fields[$campo] = $valor;
        }
    }

    return $fields;
}

// Função que busca o webhook base do cliente no banco usando o ID do cliente
function buscarWebhook($clienteId, $tipo)
{
    $host = 'localhost';
    $dbname = 'kw24co49_api_kwconfig';
    $usuario = 'kw24co49_kw24';
    $senha = 'BlFOyf%X}#jXwrR-vi';

    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $usuario, $senha);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $stmt = $pdo->prepare("SELECT webhook_{$tipo} FROM clientes_api WHERE origem = :cliente LIMIT 1");
        $stmt->bindParam(':cliente', $clienteId);
        $stmt->execute();
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

        return $resultado ? $resultado["webhook_{$tipo}"] : null;

    } catch (PDOException $e) {
        error_log("Erro DB: " . $e->getMessage());
        return null;
    }
}

// Função que cria o negócio (card) no Bitrix24 usando a API
function criarNegocio($dados)
{
    $log = "==== NOVA REQUISIÇÃO ====\nEntrada: " . json_encode($dados) . "\n";

    if (!isset($dados['spa']) || empty($dados['spa'])) {
        return [
            'erro' => 'SPA (entidade) não informada.',
            'debug' => $log
        ];
    }

    $cliente = $_GET['cliente'] ?? '';
    $webhookBase = buscarWebhook($cliente, 'deal');

    if (!$webhookBase) {
        return [
            'erro' => 'Cliente não autorizado ou webhook não cadastrado.',
            'cliente' => $cliente,
            'debug' => $log
        ];
    }

    $url = $webhookBase . '/crm.item.add.json';

    $spa = $dados['spa'];
    unset($dados['spa']);

    $fields = formatarCampos($dados);

    // categoryId vai dentro de fields
    if (isset($dados['CATEGORY_ID'])) {
        $fields['categoryId'] = $dados['CATEGORY_ID'];
    }

    $params = [
        'entityTypeId' => $spa,
        'fields' => $fields
    ];

    $postData = http_build_query($params);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $resposta = curl_exec($ch);
    $curlErro = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $log .= "URL usada: $url\nDados enviados: $postData\nHTTP Code: $httpCode\nErro cURL: $curlErro\nResposta: $resposta\n";

    $respostaJson = json_decode($resposta, true);

    if (isset($respostaJson['result']['item']['id'])) {
        return [
            'sucesso' => true,
            'id' => $respostaJson['result']['item']['id'],
            'urlUsada' => $url,
            'camposEnviados' => $fields,
            'debug' => $log
        ];
    } else {
        return [
            'erro' => 'Erro ao criar negócio',
            'urlUsada' => $url,
            'respostaCompleta' => $respostaJson,
            'debug' => $log
        ];
    }
}
