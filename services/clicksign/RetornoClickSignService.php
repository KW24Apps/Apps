<?php
namespace Services\ClickSign;

require_once __DIR__ . '/loader.php';

use Helpers\ClickSignHelper;
use Helpers\LogHelper;
use Repositories\ClickSignDAO;
use Enums\ClickSignCodes;
use Helpers\BitrixDealHelper;
use Helpers\UtilHelpers;

class RetornoClickSignService
{
    public static function processarWebhook(array $requestData, string $rawBody, ?string $headerSignature): array
    {
        $documentKey = $requestData['document']['key'] ?? null;
        if (empty($documentKey)) {
            $codigoRetorno = ClickSignCodes::WEBHOOK_PARAMS_AUSENTES;
            $mensagem = UtilService::getMessageDescription($codigoRetorno);
            LogHelper::logClickSign($mensagem . " - Parâmetros obrigatórios ausentes.", 'service');
            return ['success' => false, 'mensagem' => $mensagem];
        }

        $dadosAssinatura = ClickSignDAO::obterAssinaturaClickSign($documentKey);
        if (!$dadosAssinatura) {
            $codigoRetorno = ClickSignCodes::DOCUMENTO_NAO_ENCONTRADO_BD;
            $mensagem = UtilService::getMessageDescription($codigoRetorno);
            LogHelper::logClickSign($mensagem . " - Documento não encontrado no BD.", 'service');
            // Não podemos chamar atualizarRetornoBitrix aqui pois não temos dealId/spa
            return ['success' => false, 'mensagem' => $mensagem];
        }

        $dadosConexao = json_decode($dadosAssinatura['dados_conexao'], true);
        $GLOBALS['ACESSO_AUTENTICADO']['webhook_bitrix'] = $dadosConexao['webhook_bitrix'] ?? null;
        
        $secret = $dadosConexao['clicksign_secret'] ?? null;
        if (!ClickSignHelper::validarHmac($rawBody, $secret, $headerSignature)) {
            $codigoRetorno = ClickSignCodes::HMAC_INVALIDO;
            $mensagem = UtilService::getMessageDescription($codigoRetorno);
            LogHelper::logClickSign($mensagem . " - Assinatura HMAC inválida", 'service');
            // Não podemos chamar atualizarRetornoBitrix aqui pois não temos dealId/spa
            return ['success' => false, 'mensagem' => $mensagem];
        }

        $evento = $requestData['event']['name'] ?? null;
        LogHelper::logClickSign("Webhook recebido: $evento | Documento: $documentKey", 'service');

        switch ($evento) {
            case 'sign':
                $signerEmail = $requestData['event']['data']['signer']['email'] ?? null;
                // Re-obter os dados da assinatura para garantir que 'assinatura_processada' esteja atualizado
                // e que dados_conexao esteja completo
                $dadosAssinaturaAtualizados = ClickSignDAO::obterAssinaturaClickSign($documentKey);
                if (!$dadosAssinaturaAtualizados) {
                    $codigoRetorno = ClickSignCodes::DOCUMENTO_NAO_ENCONTRADO_BD;
                    $mensagem = UtilService::getMessageDescription($codigoRetorno);
                    LogHelper::logClickSign("ERRO: Falha ao re-obter dados da assinatura após salvar status para " . $documentKey, 'service');
                    // Não podemos chamar atualizarRetornoBitrix aqui pois não temos dealId/spa
                    return ['success' => false, 'mensagem' => $mensagem];
                }

                // Decodificar dados_conexao para obter os campos e signatários
                $consolidatedDadosConexao = json_decode($dadosAssinaturaAtualizados['dados_conexao'], true);
                $todosSignatarios = $consolidatedDadosConexao['signatarios_detalhes']['todos_signatarios'] ?? [];
                $assinaturasProcessadas = array_filter(explode(';', $dadosAssinaturaAtualizados['assinatura_processada'] ?? ''));

                // Verificar se o signatário já foi processado
        if (in_array($signerEmail, $assinaturasProcessadas)) {
            // Ação ignorada, assinatura já processada.
            return ['success' => true, 'mensagem' => 'Ação ignorada, assinatura já processada.'];
        }
                
                // Adicionar o email do signatário atual à lista de assinaturas processadas
                $assinaturasProcessadas[] = $signerEmail;
                ClickSignDAO::salvarStatus($documentKey, null, implode(';', $assinaturasProcessadas));
                
                // Atualizar localmente o array $dadosAssinaturaAtualizados com o novo status
                // para evitar uma nova consulta ao banco de dados.
                $dadosAssinaturaAtualizados['assinatura_processada'] = implode(';', $assinaturasProcessadas);

                return self::assinaturaRealizada($requestData, $dadosAssinaturaAtualizados);
            case 'deadline':
            case 'cancel':
            case 'auto_close':
                return self::documentoFechado($requestData, $dadosAssinatura);
            case 'document_closed':
                return self::documentoDisponivel($requestData, $dadosAssinatura, $dadosConexao['clicksign_token']);
            default:
                // Ação ignorada, evento recebido sem ação específica.
                return ['success' => true, 'mensagem' => 'Ação ignorada, evento recebido sem ação específica.'];
        }
    }

    private static function assinaturaRealizada(array $requestData, array $dadosAssinatura): array
    {
        LogHelper::logClickSign("Início da função assinaturaRealizada.", 'service');

        $signerEmail = $requestData['event']['data']['signer']['email'] ?? null;
        $signerName = $requestData['event']['data']['signer']['name'] ?? 'N/A';

        // Decodificar dados_conexao para obter todas as informações
        $consolidatedDadosConexao = json_decode($dadosAssinatura['dados_conexao'], true);
        
        $spa = $consolidatedDadosConexao['spa'] ?? null;
        $dealId = $consolidatedDadosConexao['deal_id'] ?? null;
        $etapaConcluida = $consolidatedDadosConexao['etapa_concluida'] ?? null;
        $campos = $consolidatedDadosConexao['campos'] ?? [];
        $todosSignatarios = $consolidatedDadosConexao['signatarios_detalhes']['todos_signatarios'] ?? [];

        $campoSignatariosAssinar = $campos['signatarios_assinar'] ?? null;
        $campoSignatariosAssinaram = $campos['signatarios_assinaram'] ?? null;
        $campoArquivoAssinado = $campos['arquivoassinado'] ?? null; // Adicionado para documentoDisponivel
        $campoRetornoBitrix = $campos['retorno'] ?? null; // Extract campo_retorno here
        LogHelper::logClickSign("RetornoClickSignService::assinaturaRealizada - campoRetornoBitrix: " . ($campoRetornoBitrix ?? 'N/A'), 'debug');

        if ($campoSignatariosAssinar && $campoSignatariosAssinaram) {
            $assinaturasProcessadas = array_filter(explode(';', $dadosAssinatura['assinatura_processada'] ?? ''));
            $idsAssinaram = [];
            foreach ($todosSignatarios as $s) {
                if (in_array($s['email'], $assinaturasProcessadas)) $idsAssinaram[] = $s['id'];
            }
            $idsAAssinar = array_diff(array_column($todosSignatarios, 'id'), $idsAssinaram);

            BitrixDealHelper::editarDeal($spa, $dealId, [
                $campoSignatariosAssinaram => array_values($idsAssinaram),
                $campoSignatariosAssinar => empty($idsAAssinar) ? '' : array_values($idsAAssinar)
            ]);
        } else {
            LogHelper::logClickSign("Não entrou na condição if (\$campoSignatariosAssinar && \$campoSignatariosAssinaram).", 'service');
        }

        $codigoRetorno = ClickSignCodes::ASSINATURA_REALIZADA;
        $mensagemCustomizadaComentario = "$signerName - $signerEmail";
        $mensagemParaRetornoFuncao = UtilService::getMessageDescription($codigoRetorno) . $mensagemCustomizadaComentario;
        
        $paramsForUpdate = ['campo_retorno' => $campoRetornoBitrix];
        UtilService::atualizarRetornoBitrix($paramsForUpdate, $spa, $dealId, true, $dadosAssinatura['document_key'], $codigoRetorno, $mensagemCustomizadaComentario);
        return ['success' => true, 'mensagem' => $mensagemParaRetornoFuncao];
    }

    private static function documentoFechado(array $requestData, array $dadosAssinatura): array
    {
        $evento = $requestData['event']['name'];
        if ($dadosAssinatura['documento_fechado_processado']) {
            // Ação ignorada, evento de documento fechado já processado.
            return ['success' => true, 'mensagem' => 'Ação ignorada, evento de documento fechado já processado.'];
        }
        ClickSignDAO::salvarStatus($dadosAssinatura['document_key'], $evento, null, true);

        $consolidatedDadosConexao = json_decode($dadosAssinatura['dados_conexao'], true);
        $campos = $consolidatedDadosConexao['campos'] ?? [];
        $campoRetornoBitrix = $campos['retorno'] ?? null;
        LogHelper::logClickSign("RetornoClickSignService::documentoFechado - campoRetornoBitrix: " . ($campoRetornoBitrix ?? 'N/A'), 'debug');

        if (in_array($evento, ['deadline', 'cancel'])) {
            $codigoRetorno = $evento === 'deadline' ? ClickSignCodes::ASSINATURA_CANCELADA_PRAZO : ClickSignCodes::ASSINATURA_CANCELADA_MANUAL;
            $mensagemCustomizadaComentario = $evento === 'deadline' ? ' - Prazo finalizado.' : ' - Cancelada manualmente.';
            $mensagemParaRetornoFuncao = UtilService::getMessageDescription($codigoRetorno) . $mensagemCustomizadaComentario;
            
            $paramsForUpdate = ['campo_retorno' => $campoRetornoBitrix];
            UtilService::atualizarRetornoBitrix($paramsForUpdate, $consolidatedDadosConexao['spa'], $consolidatedDadosConexao['deal_id'], false, $dadosAssinatura['document_key'], $codigoRetorno, $mensagemCustomizadaComentario);
            return ['success' => false, 'mensagem' => $mensagemParaRetornoFuncao];
        }
        // Ação ignorada, evento auto_close salvo.
        return ['success' => true, 'mensagem' => 'Ação ignorada, evento auto_close salvo.'];
    }

    private static function documentoDisponivel(array $requestData, array $dadosAssinatura, string $token): array
    {
        if ($dadosAssinatura['documento_disponivel_processado']) {
            // Ação ignorada, documento já disponível.
            return ['success' => true, 'mensagem' => 'Ação ignorada, documento já disponível.'];
        }
        ClickSignDAO::salvarStatus($dadosAssinatura['document_key'], null, null, null, true);

        $statusClosed = ClickSignDAO::obterAssinaturaClickSign($dadosAssinatura['document_key']);
        if (in_array($statusClosed['status_closed'] ?? '', ['deadline', 'cancel'])) {
            $mensagem = "Processamento ignorado devido ao status: {$statusClosed['status_closed']}.";
            LogHelper::logClickSign($mensagem, 'service');
            return ['success' => true, 'mensagem' => $mensagem];
        }

        // Decodificar dados_conexao para obter os campos
        $consolidatedDadosConexao = json_decode($dadosAssinatura['dados_conexao'], true);
        $campos = $consolidatedDadosConexao['campos'] ?? [];
        $campoArquivoAssinado = $campos['arquivoassinado'] ?? null;
        $campoRetornoBitrix = $campos['retorno'] ?? null;
        LogHelper::logClickSign("RetornoClickSignService::documentoDisponivel - campoRetornoBitrix: " . ($campoRetornoBitrix ?? 'N/A'), 'debug');

        $url = $requestData['document']['downloads']['signed_file_url'] ?? null;
        if (!$url) {
            $codigoRetorno = ClickSignCodes::ERRO_BAIXAR_ARQUIVO_ANEXO;
            $mensagem = UtilService::getMessageDescription($codigoRetorno);
            $paramsForUpdate = ['campo_retorno' => $campoRetornoBitrix];
            UtilService::atualizarRetornoBitrix($paramsForUpdate, $consolidatedDadosConexao['spa'], $consolidatedDadosConexao['deal_id'], false, $dadosAssinatura['document_key'], $codigoRetorno, null);
            return ['success' => false, 'mensagem' => $mensagem];
        }

        if (empty($campoArquivoAssinado)) {
            $codigoRetorno = ClickSignCodes::PROCESSO_FINALIZADO_SEM_ANEXO;
            $mensagem = UtilService::getMessageDescription($codigoRetorno);
            $paramsForUpdate = ['campo_retorno' => $campoRetornoBitrix];
            UtilService::atualizarRetornoBitrix($paramsForUpdate, $consolidatedDadosConexao['spa'], $consolidatedDadosConexao['deal_id'], true, $dadosAssinatura['document_key'], $codigoRetorno, null);
            return ['success' => true, 'mensagem' => $mensagem];
        }

        $nomeArquivo = $requestData['document']['filename'] ?? "documento_assinado.pdf";
        $arquivoBase64 = UtilHelpers::baixarArquivoBase64(['urlMachine' => $url, 'name' => $nomeArquivo]);
        if (!$arquivoBase64) {
            $codigoRetorno = ClickSignCodes::FALHA_CONVERTER_ARQUIVO;
            $mensagem = UtilService::getMessageDescription($codigoRetorno);
            $paramsForUpdate = ['campo_retorno' => $campoRetornoBitrix];
            UtilService::atualizarRetornoBitrix($paramsForUpdate, $consolidatedDadosConexao['spa'], $consolidatedDadosConexao['deal_id'], false, $dadosAssinatura['document_key'], $codigoRetorno, null);
            return ['success' => false, 'mensagem' => $mensagem];
        }

        // Remover o prefixo 'data:mime/type;base64,' antes de enviar para o Bitrix
        $base64Puro = preg_replace('/^data:[^;]+;base64,/', '', $arquivoBase64['base64']);
        $arquivoParaBitrix = [['filename' => $arquivoBase64['nome'], 'data' => $base64Puro]];
        $resultado = BitrixDealHelper::editarDeal($consolidatedDadosConexao['spa'], $consolidatedDadosConexao['deal_id'], [$campoArquivoAssinado => $arquivoParaBitrix]);

        if (isset($resultado['status']) && $resultado['status'] === 'sucesso') {
            $codigoRetorno = ClickSignCodes::PROCESSO_FINALIZADO_COM_ANEXO;
            $mensagem = UtilService::getMessageDescription($codigoRetorno);
            $paramsForUpdate = ['campo_retorno' => $campoRetornoBitrix];
            UtilService::atualizarRetornoBitrix($paramsForUpdate, $consolidatedDadosConexao['spa'], $consolidatedDadosConexao['deal_id'], true, $dadosAssinatura['document_key'], $codigoRetorno, null);
            UtilService::limparCamposBitrix($consolidatedDadosConexao['spa'], $consolidatedDadosConexao['deal_id'], $consolidatedDadosConexao);
            UtilService::moverEtapaBitrix($consolidatedDadosConexao['spa'], $consolidatedDadosConexao['deal_id'], $consolidatedDadosConexao['etapa_concluida'] ?? null);
            return ['success' => true, 'mensagem' => $mensagem];
        } else {
            $codigoRetorno = ClickSignCodes::ERRO_BAIXAR_ARQUIVO_ANEXO;
            $mensagem = UtilService::getMessageDescription($codigoRetorno);
            $paramsForUpdate = ['campo_retorno' => $campoRetornoBitrix];
            UtilService::atualizarRetornoBitrix($paramsForUpdate, $consolidatedDadosConexao['spa'], $consolidatedDadosConexao['deal_id'], false, $dadosAssinatura['document_key'], $codigoRetorno, null);
            return ['success' => false, 'mensagem' => $mensagem];
        }
    }
}
