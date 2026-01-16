<?php

date_default_timezone_set('America/Sao_Paulo');

if (!defined('NOME_APLICACAO')) {
    define('NOME_APLICACAO', 'PUBLICACOES_ONLINE_JOB');
}

require_once __DIR__ . '/../helpers/LogHelper.php';
require_once __DIR__ . '/../services/PublicacoesService.php';

use Helpers\LogHelper;
use Services\PublicacoesService;

class PublicacoesJob
{
    public static function executar()
    {
        LogHelper::gerarTraceId();

        $GLOBALS['ACESSO_AUTENTICADO']['webhook_bitrix'] =
            'https://gnapp.bitrix24.com.br/rest/21/crisc4x3epmon0aa/';

        try {
            LogHelper::logCronMonitor('INICIANDO_JOB', 'PublicacoesJob');

            $service = new PublicacoesService();

            // Variável de controle para testes ou produção
            // Use 'producao' para rodar D-1 e D, ou informe uma data específica (ex: '2026-01-14')
            $dataTeste = '2026-01-14'; 

            if ($dataTeste === 'producao') {
                $datasParaProcessar = [
                    date('Y-m-d', strtotime('-1 day')),
                    date('Y-m-d')
                ];
            } else {
                $datasParaProcessar = [$dataTeste];
            }

            $dataHoraExecucao = date('Y-m-d H:i:s');

            foreach ($datasParaProcessar as $dataConsulta) {
                LogHelper::logPublicacoes("Processando data: $dataConsulta", __METHOD__);

                $resultado = $service->fetchDailyPublications($dataConsulta);

                $correspondencias = [];
                $totalEncontrados = 0;
                $totalParaAtualizar = 0;
                $resultadoEdicao = null;

                if (
                    isset($resultado['status']) &&
                    $resultado['status'] === 'sucesso' &&
                    !empty($resultado['publicacoes'])
                ) {
                
                    $montagem = $service->montarAtualizacoesParaPublicacoes($resultado['publicacoes'], $dataConsulta);


                    $correspondencias = $montagem['correspondencias'];
                    $totalEncontrados = $montagem['total_encontrados'];
                    $totalParaAtualizar = $montagem['total_para_atualizar'];

                    if ($totalParaAtualizar > 0) {
                        // Filtra as publicações originais que correspondem aos IDs que serão atualizados
                        $publicacoesParaTimeline = [];
                        foreach ($montagem['correspondencias'] as $corresp) {
                            if ($corresp['status'] === 'Atualizar') {
                                // Encontra a publicação original no array de resultados da API
                                foreach ($resultado['publicacoes'] as $pOrig) {
                                    $numOrig = $pOrig['numeroProcesso'] ?? $pOrig['numeroProcessoCNJ'] ?? null;
                                    if ($numOrig === $corresp['processo']) {
                                        $publicacoesParaTimeline[] = $pOrig;
                                        break;
                                    }
                                }
                            }
                        }

                        $resultadoEdicao = $service->executarBatchEdicao(
                            2,
                            $montagem['ids'],
                            $montagem['fields'],
                            $publicacoesParaTimeline
                        );

                        // normaliza retorno boolean
                        if ($resultadoEdicao === true) {
                            $resultadoEdicao = [
                                'status' => 'sucesso',
                                'quantidade' => $totalParaAtualizar,
                                'ids' => implode(',', $montagem['ids']),
                                'mensagem' => 'Atualização executada com sucesso'
                            ];
                        }
                    }

                    LogHelper::logPublicacoes(
                        "Data $dataConsulta processada. Total: " . count($resultado['publicacoes']) .
                        " | Encontrados: $totalEncontrados | Para atualizar: $totalParaAtualizar",
                        __METHOD__
                    );
                }

                if (php_sapi_name() === 'cli') {
                    echo "\n====================================================\n";
                    echo "RELATÓRIO DE INTEGRAÇÃO - PUBLICAÇÕES ONLINE\n";
                    echo "Data da Consulta: $dataConsulta\n";
                    echo "Total de Publicações: " . (isset($resultado['publicacoes']) ? count($resultado['publicacoes']) : 0) . "\n";
                    echo "Total de Cards encontrados no Bitrix24: $totalEncontrados\n";
                    echo "Total de Cards para atualizar no Bitrix24: $totalParaAtualizar\n";
                    echo "Data/Hora Execucao (job): $dataHoraExecucao\n";
                    echo "Data de Controle (cards): $dataConsulta\n";
                    echo "====================================================\n\n";

                    if (!empty($correspondencias)) {
                        echo "Lista de Correspondência:\n";
                        foreach ($correspondencias as $item) {
                            $status = $item['status'] ?: '—';
                            echo "- {$item['processo']} - ID Bitrix: {$item['id_bitrix']} | Status: {$status}\n";
                        }

                        if (is_array($resultadoEdicao)) {
                            echo "\n--- Resultado da Atualizacao (Batch) ---\n";
                            echo "Status: {$resultadoEdicao['status']}\n";
                            echo "Quantidade: {$resultadoEdicao['quantidade']}\n";
                            echo "IDs: {$resultadoEdicao['ids']}\n";
                            echo "Mensagem: {$resultadoEdicao['mensagem']}\n";
                        }
                    } else {
                        echo "Nenhuma publicação encontrada ou erro na API para esta data.\n";
                    }

                    echo "\n====================================================\n";
                }
            }

            LogHelper::logCronMonitor('EXECUCAO_FINALIZADA', 'PublicacoesJob');

        } catch (\Throwable $e) {
            LogHelper::logCronMonitor('ERRO_FATAL', 'PublicacoesJob');
            LogHelper::logPublicacoes("EXCEÇÃO NO JOB: " . $e->getMessage(), __METHOD__);
            if (php_sapi_name() === 'cli') {
                echo "ERRO: " . $e->getMessage() . "\n";
            }
        }
    }
}

PublicacoesJob::executar();
