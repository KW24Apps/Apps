<?php

class BitrixHelper


{
    // Envia requisição para API Bitrix com endpoint e parâmetros fornecidos
    public static function chamarApi($endpoint, $params, $opcoes = [])
    {
        $webhookBase = $opcoes['webhook'] ?? '';
        if (!$webhookBase) {
            return ['error' => 'Webhook não informado'];
        }

        $logAtivo = $opcoes['log'] ?? false;
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
            $log = "==== CHAMADA API ====\n";
            $log .= "Endpoint: $endpoint\nURL: $url\nDados: $postData\nHTTP: $httpCode\nErro: $curlErro\nResposta: $resposta\n";
            $log .= "Campos enviados (params): " . print_r($params, true) . "\n";
            file_put_contents(__DIR__ . '/../logs/editar_negocio.log', $log, FILE_APPEND);
        }

        return $respostaJson;
    }
    
    // Formata os campos conforme o padrão esperado pelo Bitrix (camelCase)
    public static function formatarCampos($dados)
    {
        $fields = [];

        foreach ($dados as $campo => $valor) {
            if (strpos($campo, 'UF_CRM_') === 0) {
                $chaveConvertida = 'ufCrm' . substr($campo, 7);
                $fields[$chaveConvertida] = $valor;
            } else {
                $fields[$campo] = $valor;
            }
        }

        return $fields;
    }

    // Cria um negócio no Bitrix24 via API
    public static function criarNegocio($dados)
    {
        //$dados = $_POST ?: $_GET;
        $spa = $dados['spa'] ?? null;
        $categoryId = $dados['CATEGORY_ID'] ?? null;
        $webhook = $dados['webhook'] ?? null;

        unset($dados['cliente'], $dados['spa'], $dados['CATEGORY_ID'], $dados['webhook']);

        $fields = self::formatarCampos($dados);
        if ($categoryId) {
            $fields['categoryId'] = $categoryId;
        }

        $params = [
            'entityTypeId' => $spa,
            'fields' => $fields
        ];

        $resultado = self::chamarApi('crm.item.add', $params, [
            'webhook' => $webhook,
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
            'debug' => $resultado,
            'error' => $resultado['error_description'] ?? 'Erro desconhecido ao criar negócio.'
        ];
    }

    // Edita um negócio existente no Bitrix24 via API
    public static function editarNegociacao($dados = [])
    {
        $spa = $dados['spa'] ?? null;
        $dealId = $dados['deal'] ?? null;
        $webhook = $dados['webhook'] ?? null;

        unset($dados['cliente'], $dados['spa'], $dados['deal'], $dados['webhook']);

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
            'webhook' => $webhook,
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
        $spa = $filtros['spa'] ?? 0;
        $dealId = $filtros['deal'] ?? null;
        $webhook = $filtros['webhook'] ?? null;

        if (!$dealId || !$webhook) {
            return ['erro' => 'ID do negócio ou webhook não informado.'];
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
            'webhook' => $webhook,
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

    //Criar tarefa automatica 
    public static function criarTarefaAutomatica(array $dados)
    {
    $titulo = $dados['titulo'] ?? null;
    $descricao = $dados['descricao'] ?? null;
    $responsavel = $dados['responsavel'] ?? null;
    $prazo = (int) ($dados['prazo'] ?? 0);
    $webhook = $dados['webhook'] ?? null;

    if (!$titulo || !$descricao || !$responsavel || !$prazo || !$webhook) {
        return ['erro' => 'Parâmetros obrigatórios ausentes.'];
    }

    $dataConclusao = self::calcularDataUtil($prazo);
    if (in_array($dataConclusao->format('N'), [6, 7])) {
        $dataConclusao->modify('next monday');
    }

    $params = [
        'fields' => [
            'TITLE' => $titulo,
            'DESCRIPTION' => $descricao,
            'RESPONSIBLE_ID' => $responsavel,
            'DEADLINE' => $dataConclusao->format('Y-m-d'),
        ]
    ];

    return self::chamarApi('tasks.task.add', $params, [
        'webhook' => $webhook,
        'log' => true
    ]);
}

private static function calcularDataUtil(int $dias): DateTime
{
    $data = new DateTime();
    $adicionados = 0;

    while ($adicionados < $dias) {
        $data->modify('+1 day');
        $diaSemana = $data->format('N');
        if ($diaSemana < 6) {
            $adicionados++;
        }
    }
    return $data;
}
    // Consulta múltiplas empresas organizadas por campo de origem
    public static function consultarEmpresas(array $campos, string $webhook)
    {
        $resultado = [];

        foreach ($campos as $origem => $ids) {
            $resultado[$origem] = [];

            foreach ((array)$ids as $id) {
                $resposta = self::consultarEmpresa([
                    'empresa' => $id,
                    'webhook' => $webhook
                ]);

                $log = "[consultarEmpresas] Origem: $origem | ID: $id | Resultado: " . json_encode($resposta) . PHP_EOL;
                file_put_contents(__DIR__ . '/../logs/bitrix_sync.log', $log, FILE_APPEND);

                if (!isset($resposta['erro'])) {
                    $resultado[$origem][] = $resposta;
                }
            }
        }

        return $resultado;
    }


    // Consulta múltiplos contatos organizados por campo de origem
    public static function consultarContatos(array $campos, string $webhook)
    {
        $resultado = [];

        foreach ($campos as $origem => $ids) {
            $resultado[$origem] = [];

            foreach ((array)$ids as $id) {
                $resposta = self::consultarContato([
                    'contato' => $id,
                    'webhook' => $webhook
                ]);

                $log = "[consultarContatos] Origem: $origem | ID: $id | Resultado: " . json_encode($resposta) . PHP_EOL;
                file_put_contents(__DIR__ . '/../logs/bitrix_sync.log', $log, FILE_APPEND);

                if (!isset($resposta['erro'])) {
                    $resultado[$origem][] = $resposta;
                }
            }
        }

        return $resultado;
    }




}