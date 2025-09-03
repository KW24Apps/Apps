<?php
namespace Services\ClickSign;

require_once __DIR__ . '/loader.php';

use Helpers\ClickSignHelper;
use Helpers\LogHelper;
use Helpers\BitrixDealHelper;
use Helpers\BitrixHelper;
use Enums\ClickSignCodes;

class DocumentoService
{
    public static function cancelarDocumento(array $params): array
    {
        $authData = self::getAuthAndDocumentKey($params);
        if (!$authData['success'] || empty($authData['documentKey'])) {
            return $authData;
        }
        
        $documentKey = $authData['documentKey'];
        $resultado = ClickSignHelper::cancelarDocumento($documentKey);

        // Extract campo_retorno from the original params for updating Bitrix
        $paramsForUpdate = $authData['paramsForUpdate']; // Usar o paramsForUpdate já com campo_retorno
        $entityId = $authData['spa'];
        $id = $authData['dealId'];

        if (isset($resultado['document'])) {
            $codigoRetorno = ClickSignCodes::ASSINATURA_CANCELADA_MANUAL;
            $mensagemParaRetornoFuncao = UtilService::getMessageDescription($codigoRetorno);
            UtilService::atualizarRetornoBitrix($paramsForUpdate, $entityId, $id, true, $documentKey, $codigoRetorno, null);
            LogHelper::logClickSign($mensagemParaRetornoFuncao . " - Documento $documentKey cancelado com sucesso.", 'service');
            return ['success' => true, 'mensagem' => $mensagemParaRetornoFuncao];
        } else {
            $codigoRetorno = ClickSignCodes::FALHA_CANCELAR_DOCUMENTO;
            $erro = $resultado['errors'][0] ?? 'Erro desconhecido ao cancelar.';
            $mensagemParaRetornoFuncao = UtilService::getMessageDescription($codigoRetorno);
            UtilService::atualizarRetornoBitrix($paramsForUpdate, $entityId, $id, false, $documentKey, $codigoRetorno, null);
            LogHelper::logClickSign($mensagemParaRetornoFuncao . " - Falha ao cancelar documento ($documentKey): $erro", 'service');
            return ['success' => false, 'mensagem' => $mensagemParaRetornoFuncao, 'details' => $resultado];
        }
    }

    public static function atualizarDataDocumento(array $params): array
    {
        $authData = self::getAuthAndDocumentKey($params);
        if (!$authData['success'] || empty($authData['documentKey'])) {
            return $authData;
        }

        $documentKey = $authData['documentKey'];
        $fieldsConfig = $authData['fieldsConfig'];
        $entityId = $authData['spa']; // Usar entityId do authData
        $id = $authData['dealId']; // Usar id do authData

        LogHelper::logClickSign("DocumentoService::atualizarDataDocumento - Parâmetros recebidos: " . json_encode($params), 'debug');
        $campoDataOriginal = $fieldsConfig['data'] ?? null;
        if (empty($campoDataOriginal)) {
            $codigoRetorno = ClickSignCodes::DATA_LIMITE_OBRIGATORIA;
            $mensagem = UtilService::getMessageDescription($codigoRetorno) . ' - Campo "data" não configurado para esta SPA.';
            return ['success' => false, 'mensagem' => $mensagem];
        }

        $dealData = BitrixDealHelper::consultarDeal($entityId, $id, [$campoDataOriginal]);
        $campoDataFormatado = array_key_first(BitrixHelper::formatarCampos([$campoDataOriginal => null]));
        $novaData = $dealData['result'][$campoDataFormatado]['valor'] ?? null;

        if (empty($novaData)) {
            $codigoRetorno = ClickSignCodes::DATA_LIMITE_OBRIGATORIA;
            $mensagem = UtilService::getMessageDescription($codigoRetorno) . ' - Campo de data não encontrado ou vazio no Deal.';
            return ['success' => false, 'mensagem' => $mensagem];
        }
        
        $novaDataFormatada = substr($novaData, 0, 10);
        $payload = ['document' => ['deadline_at' => $novaDataFormatada]];
        $resultado = ClickSignHelper::atualizarDocumento($documentKey, $payload);

        // Usar o paramsForUpdate já com campo_retorno do authData
        $paramsForUpdate = $authData['paramsForUpdate'];
        LogHelper::logClickSign("DocumentoService::atualizarDataDocumento - campoRetornoBitrix para atualização: " . ($paramsForUpdate['campo_retorno'] ?? 'N/A'), 'debug');

        if (isset($resultado['document'])) {
            $codigoRetorno = ClickSignCodes::DATA_ATUALIZADA_MANUALMENTE;
            $dataParaMensagem = date('d/m/Y', strtotime($novaDataFormatada));
            $mensagemCustomizadaComentario = "$dataParaMensagem.";
            $mensagemParaRetornoFuncao = UtilService::getMessageDescription($codigoRetorno) . $mensagemCustomizadaComentario;
            UtilService::atualizarRetornoBitrix($paramsForUpdate, $entityId, $id, true, $documentKey, $codigoRetorno, $mensagemCustomizadaComentario);
            LogHelper::logClickSign($mensagemParaRetornoFuncao, 'service');
            return ['success' => true, 'mensagem' => $mensagemParaRetornoFuncao];
        } else {
            $codigoRetorno = ClickSignCodes::FALHA_ATUALIZAR_DOCUMENTO;
            $erro = $resultado['errors'][0] ?? 'Erro desconhecido ao atualizar data.';
            $mensagemParaRetornoFuncao = UtilService::getMessageDescription($codigoRetorno);
            $mensagemCustomizadaComentario = " - Falha ao atualizar data do documento: $erro";
            UtilService::atualizarRetornoBitrix($paramsForUpdate, $entityId, $id, false, $documentKey, $codigoRetorno, null);
            LogHelper::logClickSign($mensagemParaRetornoFuncao . $mensagemCustomizadaComentario, 'service');
            return ['success' => false, 'mensagem' => $mensagemParaRetornoFuncao, 'details' => $resultado];
        }
    }

    private static function getAuthAndDocumentKey(array $params): array
    {
        LogHelper::logClickSign("DocumentoService::getAuthAndDocumentKey - Parâmetros recebidos: " . json_encode($params), 'debug');
        $entityId = $params['spa'] ?? null;
        $id = $params['deal'] ?? null;

        // Extrai campo_retorno dos params de entrada para uso imediato em caso de erro
        $campoRetornoBitrix = $params['retorno'] ?? $params['campo_retorno'] ?? null;
        $paramsForUpdate = ['campo_retorno' => $campoRetornoBitrix];
        LogHelper::logClickSign("DocumentoService::getAuthAndDocumentKey - campoRetornoBitrix inicial: " . $campoRetornoBitrix, 'debug');

        if (empty($id) || empty($entityId)) {
            $codigoRetorno = ClickSignCodes::PARAMS_AUSENTES;
            $mensagem = UtilService::getMessageDescription($codigoRetorno);
            UtilService::atualizarRetornoBitrix($paramsForUpdate, $entityId, $id, false, null, $codigoRetorno, null);
            LogHelper::logClickSign($mensagem . ' (deal, spa) ausentes.', 'service');
            return ['success' => false, 'mensagem' => $mensagem . ' (deal, spa) ausentes.'];
        }

        $configExtra = $GLOBALS['ACESSO_AUTENTICADO']['config_extra'] ?? null;
        $configJson = $configExtra ? json_decode($configExtra, true) : [];
        $spaKey = 'SPA_' . $entityId;
        $fieldsConfig = $configJson[$spaKey]['campos'] ?? [];
        $tokenClicksign = $configJson[$spaKey]['clicksign_token'] ?? null;

        // Atualiza campoRetornoBitrix com base em fieldsConfig se disponível
        $campoRetornoBitrix = $fieldsConfig['retorno'] ?? $campoRetornoBitrix;
        $paramsForUpdate = ['campo_retorno' => $campoRetornoBitrix];
        LogHelper::logClickSign("DocumentoService::getAuthAndDocumentKey - campoRetornoBitrix após fieldsConfig: " . $campoRetornoBitrix, 'debug');

        if (!$tokenClicksign) {
            $codigoRetorno = ClickSignCodes::ACESSO_NAO_AUTORIZADO;
            $mensagem = UtilService::getMessageDescription($codigoRetorno);
            UtilService::atualizarRetornoBitrix($paramsForUpdate, $entityId, $id, false, null, $codigoRetorno, null);
            LogHelper::logClickSign($mensagem . ' - Acesso não autorizado ou incompleto.', 'service');
            return ['success' => false, 'mensagem' => $mensagem . ' - Acesso não autorizado ou incompleto.'];
        }

        $campoIdClickSignOriginal = $fieldsConfig['idclicksign'] ?? null;
        if (empty($campoIdClickSignOriginal)) {
            $codigoRetorno = ClickSignCodes::TOKEN_AUSENTE; // Usando TOKEN_AUSENTE como um código genérico para campo ausente
            $mensagem = UtilService::getMessageDescription($codigoRetorno);
            UtilService::atualizarRetornoBitrix($paramsForUpdate, $entityId, $id, false, null, $codigoRetorno, null);
            LogHelper::logClickSign($mensagem . ' - Campo "idclicksign" não configurado para esta SPA.', 'service');
            return ['success' => false, 'mensagem' => $mensagem . ' - Campo "idclicksign" não configurado para esta SPA.'];
        }
        
        $dealData = BitrixDealHelper::consultarDeal($entityId, $id, [$campoIdClickSignOriginal]);
        $campoIdClickSignFormatado = array_key_first(BitrixHelper::formatarCampos([$campoIdClickSignOriginal => null]));
        $documentKey = $dealData['result'][$campoIdClickSignFormatado]['valor'] ?? null;

        if (empty($documentKey)) {
            $codigoRetorno = ClickSignCodes::DOCUMENTO_NAO_ENCONTRADO_BD;
            $mensagem = UtilService::getMessageDescription($codigoRetorno);
            UtilService::atualizarRetornoBitrix($paramsForUpdate, $entityId, $id, false, null, $codigoRetorno, null);
            LogHelper::logClickSign($mensagem . ' - Ação ignorada, nenhum documento para atualizar.', 'service');
            return ['success' => false, 'mensagem' => $mensagem . ' - Ação ignorada, nenhum documento para atualizar.'];
        }

        LogHelper::logClickSign("DocumentoService::getAuthAndDocumentKey - campoRetornoBitrix final: " . $campoRetornoBitrix, 'debug');

        return [
            'success' => true,
            'documentKey' => $documentKey,
            'fieldsConfig' => $fieldsConfig,
            'spa' => $entityId,
            'dealId' => $id,
            'paramsForUpdate' => $paramsForUpdate
        ];
    }
}
