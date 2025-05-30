<?php

// Função para formatar os campos conforme esperado pelo Bitrix
function formatarCampos($dados)
{
    $formatado = [];

    foreach ($dados as $campo => $valor) {
        $campoFormatado = strtoupper($campo);
        $formatado[$campoFormatado] = $valor;
    }

    return $formatado;
}

// Função que busca o webhook base do cliente no banco usando o domínio do Referer
function buscarWebhook($referer, $tipo)
{
    $host = 'localhost';
    $dbname = 'kw24co49_api_kwconfig';
    $usuario = 'kw24co49_kw24';
    $senha = 'BlFOyf%X}#jXwrR-vi'; // 🔐 Substitua pela senha real do banco

    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $usuario, $senha);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $dominio = parse_url($referer, PHP_URL_HOST);

        $stmt = $pdo->prepare("SELECT webhook_{$tipo} FROM clientes_api WHERE origem = :dominio LIMIT 1");
        $stmt->bindParam(':dominio', $dominio);
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
    if (!isset($dados['spa']) || empty($dados['spa'])) {
        return ['erro' => 'SPA (entidade) não informada.'];
    }

    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    $webhookBase = buscarWebhook($referer, 'deal');

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

    $camposFormatados = formatarCampos($dados);

    $params = [
        'entityTypeId' => $spa,
        'fields' => $camposFormatados
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $resposta = curl_exec($ch);
    curl_close($ch);

    $respostaJson = json_decode($resposta, true);

    if (isset($respostaJson['result']['item']['id'])) {
        return [
            'sucesso' => true,
            'id' => $respostaJson['result']['item']['id'],
            'camposEnviados' => $camposFormatados
        ];
    } else {
        return [
            'erro' => 'Erro ao criar negócio',
            'respostaCompleta' => $respostaJson
        ];
    }
}
