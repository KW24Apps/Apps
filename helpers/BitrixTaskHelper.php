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

    // listar tarefas
    public static function listarTarefas($filtros = [], $campos = ['*'])
    {
        $todasTarefas = [];
        $pagina = 0;
        $totalProcessados = 0;
        $continuarPaginacao = true;

        while ($continuarPaginacao) {
            $params = [
                'select' => $campos,
                'start' => $pagina * 50
            ];

            if (!empty($filtros)) {
                $params['filter'] = $filtros;
            }

            $resultado = BitrixHelper::chamarApi('tasks.task.list', $params, ['log' => true]);

            if (!isset($resultado['result']['tasks'])) {
                return [
                    'success' => false,
                    'debug' => $resultado,
                    'error' => $resultado['error_description'] ?? 'Erro desconhecido ao listar tarefas.'
                ];
            }

            $tarefasPagina = $resultado['result']['tasks'];
            $quantidadePagina = count($tarefasPagina);
            
            $todasTarefas = array_merge($todasTarefas, $tarefasPagina);
            $totalProcessados += $quantidadePagina;
            $pagina++;

            if ($quantidadePagina > 0) {
                \Helpers\LogHelper::logBitrixHelpers(
                    "Página {$pagina} processada - {$quantidadePagina} tarefas encontradas. Total acumulado: {$totalProcessados}",
                    "BitrixTaskHelper::listarTarefas"
                );
            }

            if ($quantidadePagina < 50) {
                $continuarPaginacao = false;
            }
        }

        return [
            'success' => true,
            'tasks' => $todasTarefas,
            'total' => $totalProcessados,
            'paginas_processadas' => $pagina
        ];
    }

    // Consulta os campos disponíveis para tarefas no Bitrix24
    public static function consultarCamposTask(): array
    {
        $resultado = BitrixHelper::chamarApi('tasks.task.getFields', [], [
            'log' => true
        ]);

        if (isset($resultado['result'])) {
            return [
                'success' => true,
                'result' => $resultado['result']  // Mantém a estrutura original
            ];
        }

        return [
            'success' => false,
            'debug' => $resultado,
            'error' => $resultado['error_description'] ?? 'Erro ao consultar campos de tarefas.'
        ];
    }

    // Mapeia valores enumerados de campos de tarefas para seus textos correspondentes
    public static function mapearValoresEnumeradosTask($dados, $fields)
    {
        foreach ($fields as $campo => $definicaoCampo) {
            if (!isset($dados[$campo])) {
                continue;
            }
            
            // Para tarefas, verifica se existe 'values' (estrutura diferente dos SPAs)
            if (isset($definicaoCampo['values']) && is_array($definicaoCampo['values'])) {
                // Estrutura: "PRIORITY": {"values": {"2": "Alta", "1": "Normal"}}
                $mapa = $definicaoCampo['values'];
                
                // Troca os valores numéricos por textos
                if (is_array($dados[$campo])) {
                    $dados[$campo] = array_map(function($v) use ($mapa) {
                        return $mapa[$v] ?? $v;
                    }, $dados[$campo]);
                } else {
                    $dados[$campo] = $mapa[$dados[$campo]] ?? $dados[$campo];
                }
            }
            // Se não tem 'values', mantém o valor original (pode ser um campo só com 'title')
        }
        return $dados;
    }

    
}