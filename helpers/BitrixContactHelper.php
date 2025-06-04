<?php
require_once __DIR__ . '/../helpers/BitrixHelper.php';
class BitrixContactHelper

{
    // Consulta múltiplos contatos organizados por campo de origem
    public static function consultarContatos(array $campos, string $webhook, array $camposDesejados = [])
    {
        $resultado = [];

        foreach ($campos as $origem => $ids) {
            $resultado[$origem] = [];

            foreach ((array)$ids as $id) {
                $resposta = self::consultarContato([
                    'contato' => $id,
                    'webhook' => $webhook,
                    'campos' => $camposDesejados
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

    // Consulta único contato no Bitrix24
    public static function consultarContato($dados)
    {
        $contatoId = $dados['contato'] ?? null;
        $webhook = $dados['webhook'] ?? null;

        if (!$contatoId || !$webhook) {
            file_put_contents(__DIR__ . '/../logs/bitrix_sync.log', "[consultarContato] Parâmetros ausentes. Dados: " . json_encode($dados) . PHP_EOL, FILE_APPEND);
            return ['erro' => 'Parâmetros obrigatórios não informados.'];
        }

        $params = ['ID' => $contatoId];

        $resultado = BitrixHelper::chamarApi('crm.contact.get', $params, [
            'webhook' => $webhook,
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