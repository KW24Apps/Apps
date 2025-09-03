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
            $mensagem = ClickSignCodes::WEBHOOK_PARAMS_AUSENTES . " - Parâmetros obrigatórios ausentes.";
            LogHelper::logClickSign($mensagem, 'service');
            return ['success' => false, 'mensagem' => $mensagem];
        }

        $dadosAssinatura = ClickSignDAO::obterAssinaturaClickSign($documentKey);
        if (!$dadosAssinatura) {
            $mensagem = ClickSignCodes::DOCUMENTO_NAO_ENCONTRADO_BD . " - Documento não encontrado no BD.";
            LogHelper::logClickSign($mensagem, 'service');
            return ['success' => false, 'mensagem' => $mensagem];
        }

        $dadosConexao = json_decode($dadosAssinatura['dados_conexao'], true);
        $GLOBALS['ACESSO_AUTENTICADO']['webhook_bitrix'] = $dadosConexao['webhook_bitrix'] ?? null;
        
        $secret = $dadosConexao['clicksign_secret'] ?? null;
        if (!ClickSignHelper::validarHmac($rawBody, $secret, $headerSignature)) {
            $mensagem = ClickSignCodes::HMAC_INVALIDO . " - Assinatura HMAC inválida";
            LogHelper::logClickSign($mensagem, 'service');
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
                    LogHelper::logClickSign("ERRO: Falha ao re-obter dados da assinatura após salvar status para " . $documentKey, 'service');
                    return ['success' => false, 'mensagem' => ClickSignCodes::DOCUMENTO_NAO_ENCONTRADO_BD . " - Falha interna ao processar assinatura."];
                }

                // Decodificar dados_conexao para obter os campos e signatários
                $consolidatedDadosConexao = json_decode($dadosAssinaturaAtualizados['dados_conexao'], true);
                $todosSignatarios = $consolidatedDadosConexao['signatarios_detalhes']['todos_signatarios'] ?? [];
                $assinaturasProcessadas = array_filter(explode(';', $dadosAssinaturaAtualizados['assinatura_processada'] ?? ''));

                // Verificar se o signatário já foi processado
                if (in_array($signerEmail, $assinaturasProcessadas)) {
                    return ['success' => true, 'mensagem' => ClickSignCodes::ASSINATURA_JA_PROCESSADA . ' - Assinatura já processada.'];
                }
                
                // Adicionar o email do signatário atual à lista de assinaturas processadas
                $assinaturasProcessadas[] = $signerEmail;
                ClickSignDAO::salvarStatus($documentKey, null, implode(';', $assinaturasProcessadas));
                
                // Re-obter os dados da assinatura novamente para garantir que 'assinatura_processada' esteja atualizado
                $dadosAssinaturaAtualizados = ClickSignDAO::obterAssinaturaClickSign($documentKey);
                if (!$dadosAssinaturaAtualizados) {
                    LogHelper::logClickSign("ERRO: Falha ao re-obter dados da assinatura após salvar status para " . $documentKey, 'service');
                    return ['success' => false, 'mensagem' => ClickSignCodes::DOCUMENTO_NAO_ENCONTRADO_BD . " - Falha interna ao processar assinatura."];
                }

                return self::assinaturaRealizada($requestData, $dadosAssinaturaAtualizados);
            case 'deadline':
            case 'cancel':
            case 'auto_close':
                return self::documentoFechado($requestData, $dadosAssinatura);
            case 'document_closed':
                return self::documentoDisponivel($requestData, $dadosAssinatura, $dadosConexao['clicksign_token']);
            default:
                return ['success' => true, 'mensagem' => ClickSignCodes::EVENTO_SEM_ACAO . ' - Evento recebido sem ação específica.'];
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

        LogHelper::logClickSign("Valores dos campos (de dados_conexao) - campoSignatariosAssinar: " . ($campoSignatariosAssinar ?? 'N/A') . " | campoSignatariosAssinaram: " . ($campoSignatariosAssinaram ?? 'N/A'), 'service');
        LogHelper::logClickSign("Signatários Detalhes (de dados_conexao): " . json_encode($todosSignatarios), 'service');


        if ($campoSignatariosAssinar && $campoSignatariosAssinaram) {
            LogHelper::logClickSign("Entrou na condição if (\$campoSignatariosAssinar && \$campoSignatariosAssinaram).", 'service');
            
            $assinaturasProcessadas = array_filter(explode(';', $dadosAssinatura['assinatura_processada'] ?? ''));
            $idsAssinaram = [];
            foreach ($todosSignatarios as $s) {
                if (in_array($s['email'], $assinaturasProcessadas)) $idsAssinaram[] = $s['id'];
            }
            $idsAAssinar = array_diff(array_column($todosSignatarios, 'id'), $idsAssinaram);

            LogHelper::logClickSign("Signatários que já assinaram (idsAssinaram): " . json_encode(array_values($idsAssinaram)), 'service');
            LogHelper::logClickSign("Signatários que não assinaram (idsAAssinar): " . json_encode(array_values($idsAAssinar)), 'service');

            BitrixDealHelper::editarDeal($spa, $dealId, [
                $campoSignatariosAssinaram => array_values($idsAssinaram),
                $campoSignatariosAssinar => empty($idsAAssinar) ? [] : array_values($idsAAssinar)
            ]);
        } else {
            LogHelper::logClickSign("Não entrou na condição if (\$campoSignatariosAssinar && \$campoSignatariosAssinaram).", 'service');
        }

        $mensagem = ClickSignCodes::ASSINATURA_REALIZADA . " - Assinatura feita por $signerName - $signerEmail";
        UtilService::atualizarRetornoBitrix($dadosAssinatura, $spa, $dealId, true, $dadosAssinatura['document_key'], $mensagem);
        return ['success' => true, 'mensagem' => $mensagem];
    }

    private static function documentoFechado(array $requestData, array $dadosAssinatura): array
    {
        $evento = $requestData['event']['name'];
        if ($dadosAssinatura['documento_fechado_processado']) {
            return ['success' => true, 'mensagem' => ClickSignCodes::EVENTO_FECHADO_JA_PROCESSADO . ' - Evento de documento fechado já processado.'];
        }
        ClickSignDAO::salvarStatus($dadosAssinatura['document_key'], $evento, null, true);

        if (in_array($evento, ['deadline', 'cancel'])) {
            $codigo = $evento === 'deadline' ? ClickSignCodes::ASSINATURA_CANCELADA_PRAZO : ClickSignCodes::ASSINATURA_CANCELADA_MANUAL;
            $texto = $evento === 'deadline' ? 'Prazo finalizado.' : 'Cancelada manualmente.';
            $mensagem = "$codigo - Assinatura cancelada: $texto";
            UtilService::atualizarRetornoBitrix($dadosAssinatura, $dadosAssinatura['spa'], $dadosAssinatura['deal_id'], true, $dadosAssinatura['document_key'], $mensagem);
            return ['success' => true, 'mensagem' => $mensagem];
        }
        return ['success' => true, 'mensagem' => ClickSignCodes::EVENTO_AUTO_CLOSE_SALVO . ' - Evento auto_close salvo.'];
    }

    private static function documentoDisponivel(array $requestData, array $dadosAssinatura, string $token): array
    {
        if ($dadosAssinatura['documento_disponivel_processado']) {
            return ['success' => true, 'mensagem' => ClickSignCodes::DOCUMENTO_JA_DISPONIVEL . ' - Documento já disponível.'];
        }
        ClickSignDAO::salvarStatus($dadosAssinatura['document_key'], null, null, null, true);

        $statusClosed = ClickSignDAO::obterAssinaturaClickSign($dadosAssinatura['document_key']);
        if (in_array($statusClosed['status_closed'] ?? '', ['deadline', 'cancel'])) {
            return ['success' => true, 'mensagem' => "Processamento ignorado devido ao status: {$statusClosed['status_closed']}."];
        }

        $url = $requestData['document']['downloads']['signed_file_url'] ?? null;
        if (!$url) return ['success' => false, 'mensagem' => ClickSignCodes::ERRO_BAIXAR_ARQUIVO_ANEXO . " - URL não encontrada."];

        $campoArquivoAssinado = $dadosAssinatura['campo_arquivoassinado'];
        if (empty($campoArquivoAssinado)) {
            $mensagem = ClickSignCodes::PROCESSO_FINALIZADO_SEM_ANEXO . " - Documento assinado com sucesso.";
            UtilService::atualizarRetornoBitrix($dadosAssinatura, $dadosAssinatura['spa'], $dadosAssinatura['deal_id'], true, $dadosAssinatura['document_key'], $mensagem);
            return ['success' => true, 'mensagem' => $mensagem];
        }

        $nomeArquivo = $requestData['document']['filename'] ?? "documento_assinado.pdf";
        $arquivoBase64 = UtilHelpers::baixarArquivoBase64(['urlMachine' => $url, 'name' => $nomeArquivo]);
        if (!$arquivoBase64) return ['success' => false, 'mensagem' => ClickSignCodes::FALHA_CONVERTER_ARQUIVO . " - Erro ao baixar/converter."];

        $arquivoParaBitrix = [['filename' => $arquivoBase64['nome'], 'data' => $arquivoBase64['base64']]];
        $resultado = BitrixDealHelper::editarDeal($dadosAssinatura['spa'], $dadosAssinatura['deal_id'], [$campoArquivoAssinado => $arquivoParaBitrix]);

        if (isset($resultado['status']) && $resultado['status'] === 'sucesso') {
            $mensagem = ClickSignCodes::PROCESSO_FINALIZADO_COM_ANEXO . " - Documento assinado e anexado.";
            UtilService::atualizarRetornoBitrix($dadosAssinatura, $dadosAssinatura['spa'], $dadosAssinatura['deal_id'], true, $dadosAssinatura['document_key'], $mensagem);
            UtilService::limparCamposBitrix($dadosAssinatura['spa'], $dadosAssinatura['deal_id'], $dadosAssinatura);
            UtilService::moverEtapaBitrix($dadosAssinatura['spa'], $dadosAssinatura['deal_id'], $statusClosed['etapa_concluida'] ?? null);
            return ['success' => true, 'mensagem' => $mensagem];
        } else {
            return ['success' => false, 'mensagem' => ClickSignCodes::ERRO_BAIXAR_ARQUIVO_ANEXO . " - Falha ao anexar."];
        }
    }
}
