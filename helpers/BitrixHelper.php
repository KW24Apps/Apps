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
    // Log de entrada para depuração
    file_put_contents(__DIR__ . '/../logs/criar_negocio.log', "==== NOVA REQUISIÇÃO ====\nEntrada: " . json_encode($dados) . "\n", FILE_APPEND);

    if (!isset($dados['spa']) || empty($dados['spa'])) {
        return ['erro' => 'SPA (entidade) não informada.'];
    }

    $cliente = $_GET['cliente'] ?? ''; // Identificador do cliente na URL
    $webhookBase = buscarWebhook($cliente, 'deal');

    if (!$webhookBase) {
        return ['erro' => 'Cliente não autorizado ou webhook não cadastrado.'];
    }

    $url = $webhookBase . '/crm.item.add.json';
    
    $spa = $dados['spa'];
    unset($dados['spa']);

    if (isset($dados['CATEGORY_ID'])) {
        $dados['categoryId'] = $dados['CATEGORY_ID'];
        unset($dados['CATEGORY_ID']);
    }

    $fields = formatarCampos($dados);

    $params = [
        'entityTypeId' => $spa,
        'fields' => $fields
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $resposta = curl_exec($ch);

    // Log da URL e dos dados enviados
    file_put_contents(__DIR__ . '/../logs/criar_negocio.log', "URL usada: $url\nDados enviados: " . print_r($params, true) . "\n", FILE_APPEND);

    curl_close($ch);

    // Log da resposta do Bitrix
    file_put_contents(__DIR__ . '/../logs/criar_negocio.log', "Resposta: " . $resposta . "\n", FILE_APPEND);

    $respostaJson = json_decode($resposta, true);

    if (isset($respostaJson['result']['item']['id'])) {
        return [
            'sucesso' => true,
            'id' => $respostaJson['result']['item']['id'],
            'camposEnviados' => $fields
        ];
    } else {
        return [
            'erro' => 'Erro ao criar negócio',
            'respostaCompleta' => $respostaJson
        ];
    }
}
