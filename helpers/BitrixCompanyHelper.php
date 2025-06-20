<?php
require_once __DIR__ . '/../helpers/BitrixHelper.php';
class BitrixCompanyHelper

{
    // Consulta múltiplas empresas organizadas por campo de origem
    public static function consultarEmpresas(array $campos, string $webhook, array $camposDesejados = [])
    {
        $resultado = [];

        foreach ($campos as $origem => $ids) {
            $resultado[$origem] = [];

            foreach ((array)$ids as $id) {
                $resposta = self::consultarEmpresa([
                    'empresa' => $id,
                    'webhook' => $webhook,
                    'campos' => $camposDesejados
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

    // Consulta única empresa no Bitrix24
    public static function consultarEmpresa($dados)
    {
        $empresaId = $dados['empresa'] ?? null;
        $webhook = $dados['webhook'] ?? null;

        if (!$empresaId || !$webhook) {
            file_put_contents(__DIR__ . '/../logs/bitrix_sync.log', "[consultarEmpresa] Parâmetros ausentes. Dados: " . json_encode($dados) . PHP_EOL, FILE_APPEND);
            return ['erro' => 'Parâmetros obrigatórios não informados.'];
        }

        $params = ['ID' => $empresaId];

        $resultado = BitrixHelper::chamarApi('crm.company.get', $params, [
            'webhook' => $webhook,
            'log' => true
        ]);

        $empresa = $resultado['result'] ?? null;

        if (!empty($dados['campos']) && is_array($dados['campos'])) {
            $filtrado = [];
            foreach ($dados['campos'] as $campo) {
                if (isset($empresa[$campo])) {
                    $filtrado[$campo] = $empresa[$campo];
                }
            }
            return $filtrado;
        }

        
        return $empresa;
    }

    public static function criarEmpresa($dados)
    {
        $webhook = $dados['webhook'] ?? null;
        if (!$webhook) {
            return ['erro' => 'Webhook não informado.'];
        }

        unset($dados['webhook'], $dados['cliente']);

        $payload = [
            'fields' => $dados
        ];

        return BitrixHelper::chamarApi($webhook, 'crm.company.add', $payload);
    }

    public static function editarCamposEmpresa($dados)
    {
        $webhook = $dados['webhook'] ?? null;
        $id = $dados['id'] ?? null;

        if (!$webhook || !$id) {
            return ['erro' => 'Webhook ou ID não informado.'];
        }

        unset($dados['webhook'], $dados['cliente'], $dados['id']);

        $fields = [];
        foreach ($dados as $campo => $valor) {
            if (strpos($campo, 'UF_CRM_') === 0) {
                $fields[$campo] = $valor;
            }
        }

        if (empty($fields)) {
            return ['erro' => 'Nenhum campo UF_CRM_ enviado para atualização.'];
        }

        $payload = [
            'id' => $id,
            'fields' => $fields
        ];

        return BitrixHelper::chamarApi($webhook, 'crm.company.update', $payload);
    }

}