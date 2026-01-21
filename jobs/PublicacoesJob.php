<?php

date_default_timezone_set('America/Sao_Paulo');

if (!defined('NOME_APLICACAO')) {
    define('NOME_APLICACAO', 'PUBLICACOES_ONLINE_JOB');
}

require_once __DIR__ . '/../helpers/LogHelper.php';
require_once __DIR__ . '/../helpers/BitrixMessageHelper.php';
require_once __DIR__ . '/../services/PublicacoesService.php';

use Helpers\LogHelper;
use Helpers\BitrixMessageHelper;
use Services\PublicacoesService;

class PublicacoesJob
{
    public static function executar()
    {
        // Gera identificador único para rastreio da execução
        LogHelper::gerarTraceId();

        // Define o webhook de autenticação do Bitrix24 (Usuário 43)
        $GLOBALS['ACESSO_AUTENTICADO']['webhook_bitrix'] =
            'https://gnapp.bitrix24.com.br/rest/43/rcul3rckwkpwc4wv/';

        try {
            // Registra o início da execução no monitor de CRON
            LogHelper::logCronMonitor('INICIANDO_JOB', 'PublicacoesJob');

            // Instancia o serviço de processamento de publicações
            $service = new PublicacoesService();

            // Define se o job roda em modo produção (D e D-1) ou data fixa
            $dataTeste = 'producao'; // Alterar para 'producao' para rodar em modo produção
            // Configura o array de datas que serão consultadas
            if ($dataTeste === 'producao') {
                $datasParaProcessar = [
                    date('Y-m-d', strtotime('-1 day')),
                    date('Y-m-d')
                ];
            } else {
                $datasParaProcessar = [$dataTeste];
            }

            $dataHoraExecucao = date('Y-m-d H:i:s');
            $resumoFinal = [];

            // Inicia o processamento para cada data configurada
            foreach ($datasParaProcessar as $dataConsulta) {
                // LogHelper::logPublicacoes("Processando data: $dataConsulta", __METHOD__); // Log de sucesso removido

                // Consulta as publicações na API externa para a data específica
                $resultado = $service->fetchDailyPublications($dataConsulta);

                $correspondencias = [];
                $totalEncontrados = 0;
                $totalParaAtualizar = 0;
                $resultadoEdicao = null;

                // Verifica se a consulta retornou publicações com sucesso
                if (
                    isset($resultado['status']) &&
                    $resultado['status'] === 'sucesso' &&
                    !empty($resultado['publicacoes'])
                ) {
                
                    // Prepara o lote de atualizações comparando com o Bitrix
                    $montagem = $service->montarAtualizacoesParaPublicacoes($resultado['publicacoes'], $dataConsulta);


                    $correspondencias = $montagem['correspondencias'];
                    $totalEncontrados = $montagem['total_encontrados'];
                    $totalParaAtualizar = $montagem['total_para_atualizar'];

                    // Executa a atualização se houver cards para atualizar
                    if ($totalParaAtualizar > 0) {
                        // Filtra as publicações originais para registro na timeline
                        $publicacoesParaTimeline = [];
                        foreach ($montagem['correspondencias'] as $corresp) {
                            if ($corresp['status'] === 'Atualizar') {
                                // Localiza o objeto original da publicação
                                foreach ($resultado['publicacoes'] as $pOrig) {
                                    $numOrig = $pOrig['numeroProcesso'] ?? $pOrig['numeroProcessoCNJ'] ?? null;
                                    if ($numOrig === $corresp['processo']) {
                                        $publicacoesParaTimeline[] = $pOrig;
                                        break;
                                    }
                                }
                            }
                        }

                        // Realiza a edição em massa dos cards no Bitrix (Lote de 4 conforme solicitado)
                        $resultadoEdicao = $service->executarBatchEdicao(
                            2,
                            $montagem['ids'],
                            $montagem['fields'],
                            $publicacoesParaTimeline,
                            4
                        );

                        // Pausa curta para garantir indexação antes da próxima data
                        if (isset($resultadoEdicao['status']) && $resultadoEdicao['status'] === 'sucesso') {
                            sleep(1);
                        }

                        // Normaliza o retorno da operação de edição
                        if ($resultadoEdicao === true) {
                            $resultadoEdicao = [
                                'status' => 'sucesso',
                                'quantidade' => $totalParaAtualizar,
                                'ids' => implode(',', $montagem['ids']),
                                'mensagem' => 'Atualização executada com sucesso'
                            ];
                        }
                    }

                    // Registra o resumo do processamento da data no log
                    LogHelper::logPublicacoes(
                        "Data $dataConsulta processada. Total: " . count($resultado['publicacoes']) .
                        " | Encontrados: $totalEncontrados | Para atualizar: $totalParaAtualizar",
                        __METHOD__
                    );

                    // Ordena as correspondências por número de processo para agrupar duplicados
                    usort($correspondencias, function($a, $b) {
                        return strcmp((string)$a['processo'], (string)$b['processo']);
                    });

                    $listaProcessos = "";
                    foreach ($correspondencias as $item) {
                        $statusOriginal = $item['status'] ?? 'Vazio';
                        
                        // Remove do relatório os processos que não tiveram novas publicações
                        if ($statusOriginal === 'Já Atualizado') {
                            continue;
                        }

                        $statusFormatado = '';
                        if ($statusOriginal === 'Atualizar') {
                            $statusFormatado = "🆕 Atualizado";
                        } else {
                            $statusFormatado = "❌ Registro não localizado no sistema";
                        }

                        $idBitrixRaw = ($item['id_bitrix'] && $item['id_bitrix'] !== 'Vazio') ? $item['id_bitrix'] : null;
                        $idBitrix = $idBitrixRaw ? "[URL=https://gnapp.bitrix24.com.br/crm/deal/details/{$idBitrixRaw}/]{$idBitrixRaw}[/URL]" : '—';
                        
                        // Tenta formatar o número do processo se for CNJ (20 dígitos)
                        $processoFormatado = $item['processo'];
                        if (strlen(preg_replace('/\D/', '', $processoFormatado)) === 20) {
                            $n = preg_replace('/\D/', '', $processoFormatado);
                            $processoFormatado = substr($n, 0, 7) . '-' . substr($n, 7, 2) . '.' . substr($n, 9, 4) . '.' .
                                               substr($n, 13, 1) . '.' . substr($n, 14, 2) . '.' . substr($n, 16, 4);
                        }

                        $idWs = $item['id_ws'] ?? '—';
                        // Formato: Processo | Bitrix | IDPO | Status
                        $listaProcessos .= "• Processo nº {$processoFormatado} | Bitrix: $idBitrix | IDPO: $idWs | $statusFormatado\n";
                    }

                    if (!empty($listaProcessos)) {
                        $resumoFinal[] = "📅 *Data: " . date('d/m/Y', strtotime($dataConsulta)) . "*\n" . $listaProcessos;
                    }
                }

                    if (php_sapi_name() === 'cli') {
                        echo "\n====================================================\n";
                        echo "RELATÓRIO DE INTEGRAÇÃO - PUBLICAÇÕES ONLINE\n";
                        echo "Data da Consulta: $dataConsulta\n";
                        echo "Total de Publicações: " . (isset($resultado['publicacoes']) ? count($resultado['publicacoes']) : 0) . "\n";
                        echo "Total de Cards encontrados no Bitrix24: $totalEncontrados\n";
                        echo "Total de Cards para atualizar no Bitrix24: $totalParaAtualizar\n";
                        echo "Data/Hora Execucao (job): $dataHoraExecucao\n";
                        echo "Validação: Por IDWS (Histórico de IDs)\n";
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

            // Envia mensagem de resumo para o chat do grupo
            if (!empty($resumoFinal)) {
                $msgChat = "✅ *Processamento de Publicações Concluído*\n\n";
                $msgChat .= implode("\n\n", $resumoFinal);
                $msgChat .= "\n\n⏰ Executado em: $dataHoraExecucao";

                BitrixMessageHelper::enviarMensagem('chat46350', $msgChat);
            }

            // Registra a conclusão bem-sucedida do job
            LogHelper::logCronMonitor('EXECUCAO_FINALIZADA', 'PublicacoesJob');

        } catch (\Throwable $e) {
            // Registra falhas críticas e exceções durante a execução
            LogHelper::logCronMonitor('ERRO_FATAL', 'PublicacoesJob');
            LogHelper::logPublicacoes("EXCEÇÃO NO JOB: " . $e->getMessage(), __METHOD__);
            // Exibe o erro no terminal se estiver rodando via CLI
            if (php_sapi_name() === 'cli') {
                echo "ERRO: " . $e->getMessage() . "\n";
            }
        }
    }
}

PublicacoesJob::executar();
