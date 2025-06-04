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

}