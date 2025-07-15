<?php
namespace Helpers;

require_once __DIR__ . '/../helpers/BitrixHelper.php';

use Helpers\BitrixHelper;

class BitrixContactHelper
{
    // Consulta múltiplos contatos organizados por campo de origem
    public static function consultarContatos(array $campos, array $camposDesejados = [])
    {
        $resultado = [];

        foreach ($campos as $origem => $ids) {
            $resultado[$origem] = [];

            foreach ((array)$ids as $id) {
                $resposta = self::consultarContato([
                    'contato' => $id,
                    'campos' => $camposDesejados
                ]);

                if (!isset($resposta['erro'])) {
                    $resultado[$origem][] = $resposta;
                }
            }
        }

        return $resultado;
    }

    // Consulta único contato no Bitrix24
    public static function consultarContato($dados)
    {
        $contatoId = $dados['contato'] ?? null;

        if (!$contatoId) {
            return ['erro' => 'Parâmetros obrigatórios não informados.'];
        }
 
        $params = ['ID' => $contatoId];

        $resultado = BitrixHelper::chamarApi('crm.contact.get', $params, [
            'log' => true
        ]);

        $contato = $resultado['result'] ?? null;

        if (!empty($dados['campos']) && is_array($dados['campos'])) {
            $filtrado = [];
            foreach ($dados['campos'] as $campo) {
                if (isset($contato[$campo])) {
                    $filtrado[$campo] = $contato[$campo];
                }
            }
            return $filtrado;
        }

        return $contato;
    }


}