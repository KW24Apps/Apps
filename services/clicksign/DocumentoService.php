<?php
namespace Services\ClickSign;

require_once __DIR__ . '/loader.php';

use Helpers\ClickSignHelper;
use Helpers\LogHelper;
use Helpers\BitrixDealHelper;
use Helpers\BitrixHelper;

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
            return ['success' => true];
        } else {
            $erro = $resultado['errors'][0] ?? 'Erro desconhecido ao cancelar.';
            $mensagem = "Falha ao cancelar documento ($documentKey): $erro";
            UtilService::atualizarRetornoBitrix($params, $params['spa'], $params['deal'], false, $documentKey, $mensagem);
            return ['success' => false, 'mensagem' => $mensagem, 'details' => $resultado];
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
            return ['success' => false, 'mensagem' => 'Campo "data" não configurado para esta SPA.'];
        }

        $dealData = BitrixDealHelper::consultarDeal($entityId, $id, [$campoDataOriginal]);
        $campoDataFormatado = array_key_first(BitrixHelper::formatarCampos([$campoDataOriginal => null]));
        $novaData = $dealData['result'][$campoDataFormatado]['valor'] ?? null;

        if (empty($novaData)) {
            return ['success' => false, 'mensagem' => 'Campo de data não encontrado ou vazio no Deal.'];
        }
        
        $novaDataFormatada = substr($novaData, 0, 10);
        $payload = ['document' => ['deadline_at' => $novaDataFormatada]];
        $resultado = ClickSignHelper::atualizarDocumento($documentKey, $payload);

        if (isset($resultado['document'])) {
            $dataParaMensagem = date('d/m/Y', strtotime($novaDataFormatada));
            $mensagem = "Data do documento atualizada para $dataParaMensagem.";
            UtilService::atualizarRetornoBitrix($params, $entityId, $id, true, $documentKey, $mensagem);
            return ['success' => true, 'mensagem' => $mensagem];
        } else {
            $erro = $resultado['errors'][0] ?? 'Erro desconhecido ao atualizar data.';
            $mensagem = "Falha ao atualizar data do documento: $erro";
            UtilService::atualizarRetornoBitrix($params, $entityId, $id, false, $documentKey, $mensagem);
            return ['success' => false, 'mensagem' => $mensagem, 'details' => $resultado];
        }
    }

    private static function getAuthAndDocumentKey(array $params): array
    {
        $entityId = $params['spa'] ?? null;
        $id = $params['deal'] ?? null;

        if (empty($id) || empty($entityId)) {
            return ['success' => false, 'mensagem' => 'Parâmetros obrigatórios (deal, spa) ausentes.'];
        }

        $configExtra = $GLOBALS['ACESSO_AUTENTICADO']['config_extra'] ?? null;
        $configJson = $configExtra ? json_decode($configExtra, true) : [];
        $spaKey = 'SPA_' . $entityId;
        $fieldsConfig = $configJson[$spaKey]['campos'] ?? [];
        $tokenClicksign = $configJson[$spaKey]['clicksign_token'] ?? null;

        if (!$tokenClicksign) {
            UtilService::atualizarRetornoBitrix($params, $entityId, $id, false, null, 'Acesso não autorizado ou incompleto');
            return ['success' => false, 'mensagem' => 'Acesso não autorizado ou incompleto.'];
        }

        $campoIdClickSignOriginal = $fieldsConfig['idclicksign'] ?? null;
        if (empty($campoIdClickSignOriginal)) {
            return ['success' => false, 'mensagem' => 'Campo "idclicksign" não configurado para esta SPA.'];
        }
        
        $dealData = BitrixDealHelper::consultarDeal($entityId, $id, [$campoIdClickSignOriginal]);
        $campoIdClickSignFormatado = array_key_first(BitrixHelper::formatarCampos([$campoIdClickSignOriginal => null]));
        $documentKey = $dealData['result'][$campoIdClickSignFormatado]['valor'] ?? null;

        if (empty($documentKey)) {
            return ['success' => true, 'documentKey' => null, 'mensagem' => 'Ação ignorada, nenhum documento para atualizar.'];
        }

        return [
            'success' => true,
            'documentKey' => $documentKey,
            'fieldsConfig' => $fieldsConfig
        ];
    }
}
