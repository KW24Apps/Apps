<?php
namespace Helpers;

require_once __DIR__ . '/../helpers/BitrixHelper.php';

use Helpers\BitrixHelper;
use DateTime;

class BitrixTaskHelper
{
    // Cria uma tarefa no Bitrix24 via API de forma genérica
    public static function criarTarefa(string $TITLE, int $RESPONSIBLE_ID, int $CREATED_BY, array $fields = []): array
    {
        // Validação de campos obrigatórios
        if (empty($TITLE)) {
            return [
                'success' => false,
                'error' => 'O título da tarefa é obrigatório.'
            ];
        }
        
        if (!$RESPONSIBLE_ID) {
            return [
                'success' => false,
                'error' => 'O responsável pela tarefa é obrigatório.'
            ];
        }
        
        if (!$CREATED_BY) {
            return [
                'success' => false,
                'error' => 'O criador da tarefa é obrigatório.'
            ];
        }
        
        // Combina os campos obrigatórios com os campos adicionais
        $allFields = array_merge([
            'TITLE' => $TITLE,
            'RESPONSIBLE_ID' => $RESPONSIBLE_ID,
            'CREATED_BY' => $CREATED_BY
        ], $fields);
        
        $params = [
            'fields' => $allFields
        ];

        $resultado = BitrixHelper::chamarApi('tasks.task.add', $params, [
            'log' => true
        ]);

        if (isset($resultado['result']['task']['id'])) {
            return [
                'success' => true,
                'id' => $resultado['result']['task']['id']
            ];
        }

        return [
            'success' => false,
            'debug' => $resultado,
            'error' => $resultado['error_description'] ?? 'Erro desconhecido ao criar tarefa.'
        ];
    }

    // Edita uma tarefa existente no Bitrix24 via API
    public static function editarTarefa(int $taskId, array $fields): array
    {
        if (!$taskId || empty($fields)) {
            return [
                'success' => false,
                'error' => 'Parâmetros obrigatórios não informados.'
            ];
        }

        $params = [
            'taskId' => $taskId,
            'fields' => $fields
        ];

        $resultado = BitrixHelper::chamarApi('tasks.task.update', $params, [
            'log' => true
        ]);

        if (isset($resultado['result'])) {
            return [
                'success' => true,
                'id' => $taskId
            ];
        }

        return [
            'success' => false,
            'debug' => $resultado,
            'error' => $resultado['error_description'] ?? 'Erro desconhecido ao editar tarefa.'
        ];
    }

    // Consulta uma tarefa específica no Bitrix24 via ID
    public static function consultarTarefa(int $taskId, $fields)
    {
        if (!$taskId) {
            return [
                'success' => false,
                'error' => 'ID da tarefa não informado.'
            ];
        }

        // Normaliza campos para array e remove espaços
        if (is_string($fields)) {
            $fields = array_map('trim', explode(',', $fields));
        } else {
            $fields = array_map('trim', $fields);
        }

        if (!in_array('id', $fields)) {
            array_unshift($fields, 'id');
        }

        $params = [
            'taskId' => $taskId,
            'select' => ['*'] // Busca todos os campos
        ];

        $respostaApi = BitrixHelper::chamarApi('tasks.task.get', $params, []);

        $dadosBrutos = $respostaApi['result']['task'] ?? [];
        $resultadoFinal = [];

        foreach ($fields as $campo) {
            $resultadoFinal[$campo] = $dadosBrutos[$campo] ?? null;
        }
        
        return ['result' => ['task' => $resultadoFinal]];
    }

}