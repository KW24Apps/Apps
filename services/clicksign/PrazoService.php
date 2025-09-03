<?php
namespace Services\ClickSign;

require_once __DIR__ . '/loader.php';

use Helpers\ClickSignHelper;
use Helpers\LogHelper;
use Repositories\ClickSignDAO;
use Enums\ClickSignCodes;
use Helpers\BitrixDealHelper;

class PrazoService
{
    public static function processarAdiamentoDePrazos(): array
    {
        LogHelper::logClickSign("Início do job de adiamento de prazos.", 'service');
        $summary = ['documentos_verificados' => 0, 'documentos_adiados' => 0, 'erros' => 0];
        $amanha = date('Y-m-d', strtotime('+1 day'));

        $assinaturasAtivas = ClickSignDAO::obterAssinaturasAtivasParaVerificacao();
        if (empty($assinaturasAtivas)) {
            LogHelper::logClickSign("Nenhuma assinatura ativa encontrada para verificação.", 'service');
            return ['success' => true, 'mensagem' => 'Nenhuma assinatura ativa para processar.', 'summary' => $summary];
        }

        foreach ($assinaturasAtivas as $assinatura) {
            $dadosConexao = json_decode($assinatura['dados_conexao'], true);
            $token = $dadosConexao['clicksign_token'] ?? null;
            $documentKey = $assinatura['document_key'];

            if (empty($token)) {
                LogHelper::logClickSign("Token não encontrado para o document_key: " . $documentKey, 'service');
                $summary['erros']++;
                continue;
            }

            $GLOBALS['ACESSO_AUTENTICADO']['webhook_bitrix'] = $dadosConexao['webhook_bitrix'] ?? null;

            $documento = ClickSignHelper::buscarDocumento($documentKey, $token);
            $summary['documentos_verificados']++;

            if (isset($documento['document']) && $documento['document']['status'] === 'running' && substr($documento['document']['deadline_at'], 0, 10) === $amanha) {
                try {
                    $novaData = date_create($amanha);
                    for ($i = 0; $i < 2; $novaData->modify('+1 day')) {
                        if ($novaData->format('N') < 6) $i++;
                    }
                    $novaDataFormatada = $novaData->format('Y-m-d');

                    $resultadoUpdate = ClickSignHelper::atualizarDocumento($documentKey, ['document' => ['deadline_at' => $novaDataFormatada]], $token);

                    if (isset($resultadoUpdate['document'])) {
                        $summary['documentos_adiados']++;
                        ClickSignDAO::salvarStatus($documentKey, null, null, null, null, true);

                        $fieldsUpdate = [];
                        if (!empty($assinatura['campo_data'])) $fieldsUpdate[$assinatura['campo_data']] = $novaDataFormatada;
                        if (!empty($assinatura['campo_retorno'])) $fieldsUpdate[$assinatura['campo_retorno']] = ClickSignCodes::PRAZO_ESTENDIDO_AUTO;
                        if (!empty($fieldsUpdate)) BitrixDealHelper::editarDeal($assinatura['spa'], $assinatura['deal_id'], $fieldsUpdate);
                        
                        $codigoRetorno = ClickSignCodes::PRAZO_ESTENDIDO_AUTO;
                        $mensagemCustomizadaComentario = " - O prazo foi estendido para " . $novaData->format('d/m/Y') . ".";
                        UtilService::atualizarRetornoBitrix($assinatura, $assinatura['spa'], $assinatura['deal_id'], true, $documentKey, $codigoRetorno, $mensagemCustomizadaComentario);
                        LogHelper::logClickSign("Documento $documentKey adiado para $novaDataFormatada.", 'service');
                    } else {
                        $codigoRetorno = ClickSignCodes::FALHA_ADIAR_PRAZO;
                        $mensagemCustomizadaComentario = " - Falha ao adiar documento $documentKey.";
                        UtilService::atualizarRetornoBitrix($assinatura, $assinatura['spa'], $assinatura['deal_id'], false, $documentKey, $codigoRetorno, $mensagemCustomizadaComentario);
                        LogHelper::logClickSign($mensagemCustomizadaComentario, 'service');
                        $summary['erros']++;
                    }
                } catch (\Exception $e) {
                    $codigoRetorno = ClickSignCodes::EXCECAO_PROCESSAMENTO_PRAZO;
                    $mensagemCustomizadaComentario = " - Exceção ao processar $documentKey: " . $e->getMessage();
                    UtilService::atualizarRetornoBitrix($assinatura, $assinatura['spa'], $assinatura['deal_id'], false, $documentKey, $codigoRetorno, $mensagemCustomizadaComentario);
                    LogHelper::logClickSign($mensagemCustomizadaComentario, 'service');
                    $summary['erros']++;
                }
            }
        }

        LogHelper::logClickSign("Job de adiamento de prazos finalizado. Sumário: " . json_encode($summary), 'service');
        return ['success' => true, 'mensagem' => 'Rotina de adiamento de prazos executada.', 'summary' => $summary];
    }
}
