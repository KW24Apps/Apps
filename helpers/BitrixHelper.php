<?php

class BitrixHelper
{
    // Formata os campos conforme o padrão esperado pelo Bitrix (camelCase)
    public static function formatarCampos($dados)
    {
        $fields = [];

        foreach ($dados as $campo => $valor) {
            if (strpos($campo, 'UF_CRM_') === 0) {
                $chaveConvertida = lcfirst(str_replace('_', '', str_replace('UF_CRM_', 'ufCrm_', $campo)));
                $fields[$chaveConvertida] = $valor;
            } else {
                $fields[$campo] = $valor;
            }
        }

        return $fields;
    }

    // Busca o webhook no banco de dados com segurança
    public static function buscarWebhook($clienteId, $tipo)
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

            return $resultado ? trim($resultado["webhook_{$tipo}"]) : null;
        } catch (PDOException $e) {
            error_log("Erro DB: " . $e->getMessage());
            return null;
        }
    }

    // Cria um negócio no Bitrix24 via API
    public static function criarNegocio($dados)
    {
        $dados = $_POST ?: $_GET;
        $cliente = $_GET['cliente'] ?? '';
        $spa = $dados['spa'] ?? null;
        $categoryId = $dados['CATEGORY_ID'] ?? null;

        unset($dados['cliente'], $dados['spa'], $dados['CATEGORY_ID']);

        $fields = self::formatarCampos($dados);
        if ($categoryId) {
            $fields['categoryId'] = $categoryId;
        }

        $params = [
            'entityTypeId' => $spa,
            'fields' => $fields
        ];

        $resultado = self::chamarApi('crm.item.add', $params, [
            'cliente' => $cliente,
            'tipo' => 'deal',
            'log' => true
        ]);

        if (isset($resultado['result']['item']['id'])) {
            return [
                'success' => true,
                'id' => $resultado['result']['item']['id']
            ];
        }

        return [
            'success' => false,
            'error' => $resultado['error_description'] ?? 'Erro desconhecido ao criar negócio.'
        ];
    }

    // Edita um negócio existente no Bitrix24 via API
    public static function editarNegociacao($dados = [])
    {
        $cliente = $dados['cliente'] ?? '';
        $spa = $dados['spa'] ?? null;
        $dealId = $dados['deal'] ?? null;

        unset($dados['cliente'], $dados['spa'], $dados['deal']);

        if (!$spa || !$dealId || empty($dados)) {
            return [
                'success' => false,
                'error' => 'Parâmetros obrigatórios não informados.'
            ];
        }

        $fields = self::formatarCampos($dados);

        $params = [
            'entityTypeId' => $spa,
            'id' => (int)$dealId,
            'fields' => $fields
        ];

        $resultado = self::chamarApi('crm.item.update', $params, [
            'cliente' => $cliente,
            'tipo' => 'deal',
            'log' => true
        ]);

        if (isset($resultado['result'])) {
            return [
                'success' => true,
                'id' => $dealId
            ];
        }

        return [
            'success' => false,
            'debug' => $resultado,
            'error' => $resultado['error_description'] ?? 'Erro desconhecido ao editar negócio.'
        ];
    }

    // Consulta uma negociação específica no Bitrix24 via ID
    public static function consultarNegociacao($filtros)
    {
        $cliente = $filtros['cliente'] ?? '';
        $spa = $filtros['spa'] ?? 0;
        $dealId = $filtros['deal'] ?? null;

        if (!$dealId) {
            return ['erro' => 'ID do negócio (deal) não informado.'];
        }

        $select = ['id'];

        if (!empty($filtros['campos'])) {
            $extras = explode(',', $filtros['campos']);
            foreach ($extras as $campo) {
                $campo = trim($campo);
                if (strpos($campo, 'UF_CRM_') === 0) {
                    $convertido = 'ufCrm' . substr($campo, 7);
                    if (!in_array($convertido, $select)) {
                        $select[] = $convertido;
                    }
                }
            }
        }

        $params = [
            'entityTypeId' => $spa,
            'id' => (int)$dealId,
            'select' => $select
        ];

        $resultado = self::chamarApi('crm.item.get', $params, [
            'cliente' => $cliente,
            'tipo' => 'deal',
            'log' => false
        ]);

        if (!isset($resultado['result']['item'])) {
            return $resultado;
        }

        $item = $resultado['result']['item'];

        if (!empty($filtros['campos'])) {
            $campos = explode(',', $filtros['campos']);
            $filtrado = ['id' => $item['id'] ?? null];

            foreach ($campos as $campo) {
                $campoConvertido = 'ufCrm' . substr($campo, 7);
                if (isset($item[$campoConvertido])) {
                    $filtrado[$campoConvertido] = $item[$campoConvertido];
                }
            }

            return ['result' => ['item' => $filtrado]];
        }

        return $resultado;
    }

    // Envia requisição para API Bitrix com endpoint e parâmetros fornecidos
    public static function chamarApi($endpoint, $params, $opcoes = [])
    {
        $cliente = $opcoes['cliente'] ?? ($_GET['cliente'] ?? '');
        $tipo = $opcoes['tipo'] ?? 'deal';
        $logAtivo = $opcoes['log'] ?? false;

        $webhookBase = self::buscarWebhook($cliente, $tipo);
        $url = $webhookBase . '/' . $endpoint . '.json';
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

        $respostaJson = json_decode($resposta, true);

        if ($logAtivo) {
            $log = "==== CHAMADA API ====" . "\n";
            $log .= "Endpoint: $endpoint\nURL: $url\nDados: $postData\nHTTP: $httpCode\nErro: $curlErro\nResposta: $resposta\n";
            $log .= "Campos enviados (params): " . print_r($params, true) . "\n";
            file_put_contents(__DIR__ . '/logs/bitrix_api.log', $log, FILE_APPEND);
        }

        return $respostaJson;
    }
}
