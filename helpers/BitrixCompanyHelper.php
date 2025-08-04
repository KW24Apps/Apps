<?php
namespace Helpers;

require_once __DIR__ . '/../helpers/BitrixHelper.php';

use Helpers\BitrixHelper;

class BitrixCompanyHelper
{
    // Consulta múltiplas empresas organizadas por campo de origem
    public static function consultarEmpresas(array $campos, array $camposDesejados = [])
    {
        $resultado = [];

        foreach ($campos as $origem => $ids) {
            $resultado[$origem] = [];

            foreach ((array)$ids as $id) {
                $resposta = self::consultarEmpresa([
                    'empresa' => $id,
                    'campos' => $camposDesejados
                ]);

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

        if (!$empresaId) {
            return ['erro' => 'Parâmetros obrigatórios não informados.'];
        }

        $params = ['ID' => $empresaId];

        $resultado = BitrixHelper::chamarApi('crm.company.get', $params, [
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

    // Cria uma nova empresa no Bitrix24 via API
    public static function criarEmpresa($dados)
    {

        unset($dados['webhook'], $dados['cliente']);

        $payload = [
            'fields' => $dados
        ];

        return BitrixHelper::chamarApi('crm.company.add', $payload);
    }

    // Edita uma empresa existente no Bitrix24 via API
    public static function editarCamposEmpresa($dados)
    {
        $id = $dados['id'] ?? null;

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

        return BitrixHelper::chamarApi('crm.company.update', $payload);

    }

    // Consulta os campos de empresas no Bitrix24
    public static function consultarCamposCompany()
    {
        $respostaApi = BitrixHelper::chamarApi('crm.company.fields', []);
        return $respostaApi['result'] ?? [];
    }

}