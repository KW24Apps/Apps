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

        if (isset($resultado['document'])) {
            $codigoRetorno = ClickSignCodes::ASSINATURA_CANCELADA_MANUAL;
            $mensagemCustomizadaComentario = " - Documento $documentKey cancelado com sucesso.";
            $mensagemParaRetornoFuncao = UtilService::getMessageDescription($codigoRetorno) . $mensagemCustomizadaComentario;
            UtilService::atualizarRetornoBitrix($params, $authData['spa'], $authData['dealId'], true, $documentKey, $codigoRetorno, $mensagemCustomizadaComentario);
            return ['success' => true, 'mensagem' => $mensagemParaRetornoFuncao];
        } else {
            $codigoRetorno = ClickSignCodes::FALHA_CANCELAR_DOCUMENTO;
            $erro = $resultado['errors'][0] ?? 'Erro desconhecido ao cancelar.';
            $mensagemCustomizadaComentario = " - Falha ao cancelar documento ($documentKey): $erro";
            $mensagemParaRetornoFuncao = UtilService::getMessageDescription($codigoRetorno) . $mensagemCustomizadaComentario;
            UtilService::atualizarRetornoBitrix($params, $authData['spa'], $authData['dealId'], false, $documentKey, $codigoRetorno, $mensagemCustomizadaComentario);
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
        $entityId = $params['spa'];
        $id = $params['deal'];

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

        if (isset($resultado['document'])) {
            $codigoRetorno = ClickSignCodes::DATA_ATUALIZADA_MANUALMENTE;
            $dataParaMensagem = date('d/m/Y', strtotime($novaDataFormatada));
            $mensagemCustomizadaComentario = " - Data do documento atualizada para $dataParaMensagem.";
            $mensagemParaRetornoFuncao = UtilService::getMessageDescription($codigoRetorno) . $mensagemCustomizadaComentario;
            UtilService::atualizarRetornoBitrix($params, $entityId, $id, true, $documentKey, $codigoRetorno, $mensagemCustomizadaComentario);
            return ['success' => true, 'mensagem' => $mensagemParaRetornoFuncao];
        } else {
            $codigoRetorno = ClickSignCodes::FALHA_ATUALIZAR_DOCUMENTO;
            $erro = $resultado['errors'][0] ?? 'Erro desconhecido ao atualizar data.';
            $mensagemCustomizadaComentario = " - Falha ao atualizar data do documento: $erro";
            $mensagemParaRetornoFuncao = UtilService::getMessageDescription($codigoRetorno) . $mensagemCustomizadaComentario;
            UtilService::atualizarRetornoBitrix($params, $entityId, $id, false, $documentKey, $codigoRetorno, $mensagemCustomizadaComentario);
            return ['success' => false, 'mensagem' => $mensagemParaRetornoFuncao, 'details' => $resultado];
        }
    }

    private static function getAuthAndDocumentKey(array $params): array
    {
        $entityId = $params['spa'] ?? null;
        $id = $params['deal'] ?? null;

        if (empty($id) || empty($entityId)) {
            $codigoRetorno = ClickSignCodes::PARAMS_AUSENTES;
            $mensagem = UtilService::getMessageDescription($codigoRetorno) . ' (deal, spa) ausentes.';
            return ['success' => false, 'mensagem' => $mensagem];
        }

        $configExtra = $GLOBALS['ACESSO_AUTENTICADO']['config_extra'] ?? null;
        $configJson = $configExtra ? json_decode($configExtra, true) : [];
        $spaKey = 'SPA_' . $entityId;
        $fieldsConfig = $configJson[$spaKey]['campos'] ?? [];
        $tokenClicksign = $configJson[$spaKey]['clicksign_token'] ?? null;

        if (!$tokenClicksign) {
            $codigoRetorno = ClickSignCodes::ACESSO_NAO_AUTORIZADO;
            $mensagem = UtilService::getMessageDescription($codigoRetorno) . ' - Acesso não autorizado ou incompleto.';
            UtilService::atualizarRetornoBitrix($params, $entityId, $id, false, null, $codigoRetorno, ' - Acesso não autorizado ou incompleto.');
            return ['success' => false, 'mensagem' => $mensagem];
        }

        $campoIdClickSignOriginal = $fieldsConfig['idclicksign'] ?? null;
        if (empty($campoIdClickSignOriginal)) {
            $codigoRetorno = ClickSignCodes::TOKEN_AUSENTE; // Usando TOKEN_AUSENTE como um código genérico para campo ausente
            $mensagem = UtilService::getMessageDescription($codigoRetorno) . ' - Campo "idclicksign" não configurado para esta SPA.';
            return ['success' => false, 'mensagem' => $mensagem];
        }
        
        $dealData = BitrixDealHelper::consultarDeal($entityId, $id, [$campoIdClickSignOriginal]);
        $campoIdClickSignFormatado = array_key_first(BitrixHelper::formatarCampos([$campoIdClickSignOriginal => null]));
        $documentKey = $dealData['result'][$campoIdClickSignFormatado]['valor'] ?? null;

        if (empty($documentKey)) {
            $mensagem = 'Ação ignorada, nenhum documento para atualizar.';
            return ['success' => true, 'documentKey' => null, 'mensagem' => $mensagem];
        }

        return [
            'success' => true,
            'documentKey' => $documentKey,
            'fieldsConfig' => $fieldsConfig
        ];
    }
}
