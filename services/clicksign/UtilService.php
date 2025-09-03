<?php
namespace Services\ClickSign;

require_once __DIR__ . '/loader.php';

use Helpers\BitrixContactHelper;
use Helpers\ClickSignHelper;
use Helpers\LogHelper;
use Helpers\BitrixDealHelper;
use Helpers\BitrixHelper;
use Enums\ClickSignCodes;

class UtilService
{
    public static function processarSignatarios($idsContratante, $idsContratada, $idsTestemunhas): array
    {
        $forcarArray = function ($val) {
            if (is_array($val)) return $val;
            if ($val === null || $val === '') return [];
            return [$val];
        };

        $idsParaConsultar = [
            'contratante' => $forcarArray($idsContratante),
            'contratada'  => $forcarArray($idsContratada),
            'testemunha'  => $forcarArray($idsTestemunhas),
        ];

        $contatosConsultados = BitrixContactHelper::consultarContatos(
            $idsParaConsultar,
            ['ID', 'NAME', 'LAST_NAME', 'EMAIL']
        );

        $signatarios = ['contratante' => [], 'contratada' => [], 'testemunha' => []];
        $todosSignatariosParaJson = [];
        $qtdSignatarios = 0;

        foreach ($contatosConsultados as $papel => $listaContatos) {
            foreach ($listaContatos as $contato) {
                $idContato = $contato['ID'] ?? null;
                $nome = $contato['NAME'] ?? '';
                $sobrenome = $contato['LAST_NAME'] ?? '';
                $email = '';
                if (!empty($contato['EMAIL'])) {
                    $emailData = $contato['EMAIL'];
                    $email = is_array($emailData) ? ($emailData[0]['VALUE'] ?? '') : $emailData;
                }

                if (!$idContato || !$nome || !$sobrenome || !$email) {
                    $mensagem = ClickSignCodes::DADOS_SIGNATARIO_FALTANTES . " - Dados faltantes nos signatários ($papel)";
                    return ['success' => false, 'mensagem' => $mensagem];
                }

                $signatarioInfo = ['id' => $idContato, 'nome' => $nome, 'sobrenome' => $sobrenome, 'email' => $email];
                $signatarios[$papel][] = $signatarioInfo;
                $todosSignatariosParaJson[] = $signatarioInfo;
                $qtdSignatarios++;
            }
        }

        return [
            'success' => true,
            'signatarios' => $signatarios,
            'todosSignatariosParaJson' => $todosSignatariosParaJson,
            'qtdSignatarios' => $qtdSignatarios
        ];
    }

    public static function vincularSignatarios(string $documentKey, array $signatarios): array
    {
        $mapSignAs = ['contratante' => 'contractor', 'contratada' => 'contractee', 'testemunha' => 'witness'];
        $sucessoVinculo = true;
        $qtdVinculos = 0;
        $mapaIds = [];

        foreach ($signatarios as $papel => $listaSignatarios) {
            foreach ($listaSignatarios as $signatario) {
                $idBitrix = $signatario['id'];
                $nomeCompleto = trim($signatario['nome'] . ' ' . $signatario['sobrenome']);
                $email = $signatario['email'] ?? '';
                $signAs = $mapSignAs[$papel] ?? 'sign';

                $retornoSignatario = ClickSignHelper::criarSignatario(['name' => $nomeCompleto, 'email' => $email, 'auths' => ['email']]);
                if (empty($retornoSignatario['signer']['key'])) {
                    LogHelper::logClickSign("Falha ao criar signatário ($papel)", 'service');
                    $sucessoVinculo = false;
                    continue;
                }

                $signerKey = $retornoSignatario['signer']['key'];
                $mapaIds[] = ['id_bitrix' => $idBitrix, 'key_clicksign' => $signerKey];

                $vinculo = ClickSignHelper::vincularSignatario([
                    'document_key' => $documentKey,
                    'signer_key'   => $signerKey,
                    'sign_as'      => $signAs,
                    'message'      => "Prezado(a) $papel,\nPor favor assine o documento.\n\nAtenciosamente,\nKWCA"
                ]);

                if (!empty($vinculo['list']['request_signature_key'])) {
                    ClickSignHelper::enviarNotificacao($vinculo['list']['request_signature_key'], "Prezado(a), segue documento para assinatura.");
                }

                if (empty($vinculo['list']['key'])) {
                    LogHelper::logClickSign("Falha ao vincular signatário ($papel)", 'service');
                    $sucessoVinculo = false;
                    continue;
                }
                $qtdVinculos++;
            }
        }
        return ['success' => $sucessoVinculo, 'qtdVinculos' => $qtdVinculos, 'mapaIds' => $mapaIds];
    }

    public static function atualizarRetornoBitrix($params, $spa, $dealId, $sucesso, $documentKey, $codigoRetorno = null, $mensagemCustomizadaComentario = null)
    {
        $campoRetorno = $params['retorno'] ?? $params['campo_retorno'] ?? null;
        $campoIdClickSign = $params['idclicksign'] ?? $params['campo_idclicksign'] ?? null;

        // Mensagem para o campo Bitrix (sempre o código)
        $mensagemParaCampoBitrix = $codigoRetorno ?? ($sucesso ? ClickSignCodes::DOCUMENTO_ENVIADO : ClickSignCodes::FALHA_GRAVAR_ASSINATURA_BD);
        
        // Mensagem para o comentário na timeline (descrição do código + customização)
        $mensagemParaComentario = self::getMessageDescription($mensagemParaCampoBitrix);
        if ($mensagemCustomizadaComentario) {
            $mensagemParaComentario .= $mensagemCustomizadaComentario;
        }


        $fields = [];
        if ($campoRetorno) {
            $fields[$campoRetorno] = $mensagemParaCampoBitrix;
        }

        if ($sucesso && $campoIdClickSign && $documentKey) {
            $fields[$campoIdClickSign] = $documentKey;
        }

        if (!empty($fields)) {
            BitrixDealHelper::editarDeal($spa, $dealId, $fields);
        }

        $comentario = "Retorno ClickSign: " . $mensagemParaComentario;
        if ($documentKey) {
            $comentario .= "\nDocumento ID: " . $documentKey;
        }
        BitrixDealHelper::adicionarComentarioDeal($spa, $dealId, $comentario);
    }

    public static function limparCamposBitrix(string $spa, string $dealId, array $consolidatedDadosConexao)
    {
        $fieldsLimpeza = [];
        $camposMapeados = $consolidatedDadosConexao['campos'] ?? [];

        // Campos que devem ser limpos, usando os nomes genéricos que são chaves em $camposMapeados
        $genericFieldsToClear = [
            'contratante', 'contratada', 'testemunhas',
            'arquivoaserassinado', 'data', 'idclicksign',
            'signatarios_assinar', 'signatarios_assinaram'
        ];

        foreach ($genericFieldsToClear as $genericCampo) {
            if (isset($camposMapeados[$genericCampo]) && !empty($camposMapeados[$genericCampo])) {
                $bitrixFieldName = $camposMapeados[$genericCampo];
                $fieldsLimpeza[$bitrixFieldName] = '';
            }
        }
        
        if (!empty($fieldsLimpeza)) {
            BitrixDealHelper::editarDeal($spa, $dealId, $fieldsLimpeza);
        }
    }

    public static function moverEtapaBitrix(string $spa, string $dealId, ?string $etapaConcluidaNome)
    {
        if (!$etapaConcluidaNome) {
            LogHelper::logClickSign("AVISO: Nome da etapa concluída não fornecido para o Deal $dealId na SPA $spa.", 'service');
            return;
        }

        $etapas = BitrixHelper::consultarEtapasPorTipo($spa);
        LogHelper::logClickSign("Etapas consultadas para SPA $spa: " . json_encode($etapas), 'service');

        $statusIdAlvo = null;
        foreach ($etapas as $etapa) {
            if (isset($etapa['NAME']) && strtolower($etapa['NAME']) === strtolower($etapaConcluidaNome)) {
                $statusIdAlvo = $etapa['STATUS_ID'];
                break;
            }
        }

        if ($statusIdAlvo) {
            BitrixDealHelper::editarDeal($spa, $dealId, ['stageId' => $statusIdAlvo]);
            LogHelper::logClickSign("Deal $dealId movido para a etapa '$etapaConcluidaNome' ($statusIdAlvo)", 'service');
        } else {
            LogHelper::logClickSign("AVISO: Etapa '$etapaConcluidaNome' não encontrada para a SPA $spa. Etapas disponíveis: " . json_encode(array_column($etapas, 'NAME')), 'service');
        }
    }

    public static function getMessageDescription(string $code): string
    {
        switch ($code) {
            case ClickSignCodes::PARAMS_AUSENTES: return "Parâmetros obrigatórios ausentes.";
            case ClickSignCodes::ACESSO_NAO_AUTORIZADO: return "Acesso não autorizado ou incompleto.";
            case ClickSignCodes::DEAL_NAO_ENCONTRADO: return "Deal não encontrado.";
            case ClickSignCodes::ARQUIVO_OBRIGATORIO: return "Arquivo a ser assinado é obrigatório.";
            case ClickSignCodes::DATA_LIMITE_OBRIGATORIA: return "Data limite de assinatura é obrigatória.";
            case ClickSignCodes::DATA_LIMITE_PASSADO: return "Data limite deve ser posterior à data atual.";
            case ClickSignCodes::SIGNATARIO_OBRIGATORIO: return "Pelo menos um signatário é obrigatório.";
            case ClickSignCodes::CAMPOS_INVALIDOS: return "Campos obrigatórios ausentes ou inválidos.";
            case ClickSignCodes::DADOS_SIGNATARIO_FALTANTES: return "Dados faltantes nos signatários.";
            case ClickSignCodes::DADOS_SIGNATARIO_INCOMPLETOS: return "Dados de signatário incompletos.";
            case ClickSignCodes::DADOS_SIGNATARIO_NAO_ENCONTRADOS_EVENTO: return "Dados de signatário não encontrados no evento.";
            case ClickSignCodes::ERRO_CONVERTER_ARQUIVO: return "Erro ao converter o arquivo.";
            case ClickSignCodes::FALHA_CONVERTER_ARQUIVO: return "Falha ao converter o arquivo.";
            case ClickSignCodes::ERRO_BAIXAR_ARQUIVO_ANEXO: return "Erro ao baixar/anexar o arquivo.";
            case ClickSignCodes::FALHA_CRIAR_SIGNATARIO: return "Falha ao criar signatário.";
            case ClickSignCodes::FALHA_VINCULAR_SIGNATARIO: return "Falha ao vincular signatário.";
            case ClickSignCodes::FALHA_VINCULO_SIGNATARIOS: return "Falha em um ou mais vínculos de signatários.";
            case ClickSignCodes::DOCUMENTO_ENVIADO: return "Documento enviado para assinatura.";
            case ClickSignCodes::ASSINATURA_REALIZADA: return "Assinatura realizada.";
            case ClickSignCodes::DOCUMENTO_ASSINADO: return "Documento assinado.";
            case ClickSignCodes::ARQUIVO_ENVIADO_BITRIX: return "Arquivo enviado ao Bitrix.";
            case ClickSignCodes::PROCESSO_FINALIZADO_COM_ANEXO: return "Documento assinado e anexado.";
            case ClickSignCodes::PRAZO_ESTENDIDO_AUTO: return "Prazo estendido automaticamente.";
            case ClickSignCodes::ASSINATURA_CANCELADA_PRAZO: return "Assinatura cancelada: Prazo finalizado.";
            case ClickSignCodes::ASSINATURA_CANCELADA_MANUAL: return "Assinatura cancelada: Cancelada manualmente.";
            case ClickSignCodes::EVENTO_AUTO_CLOSE_SALVO: return "Evento auto_close salvo.";
            case ClickSignCodes::PROCESSO_FINALIZADO_SEM_ANEXO: return "Documento assinado com sucesso (sem anexo).";
            case ClickSignCodes::ASSINATURA_JA_PROCESSADA: return "Assinatura já processada.";
            case ClickSignCodes::EVENTO_FECHADO_JA_PROCESSADO: return "Evento de documento fechado já processado.";
            case ClickSignCodes::DOCUMENTO_JA_DISPONIVEL: return "Documento já disponível.";
            case ClickSignCodes::ASSINATURA_JA_EM_ANDAMENTO: return "Já existe uma assinatura em andamento para este Deal.";
            case ClickSignCodes::WEBHOOK_PARAMS_AUSENTES: return "Parâmetros obrigatórios do webhook ausentes.";
            case ClickSignCodes::HMAC_INVALIDO: return "Assinatura HMAC inválida.";
            case ClickSignCodes::DOCUMENTO_NAO_ENCONTRADO_BD: return "Documento não encontrado no BD.";
            case ClickSignCodes::CREDENCIAIS_API_NAO_CONFIGURADAS: return "Credenciais da API não configuradas.";
            case ClickSignCodes::EVENTO_SEM_ACAO: return "Evento recebido sem ação específica.";
            case ClickSignCodes::FALHA_GRAVAR_ASSINATURA_BD: return "Erro ao registrar assinatura no banco de dados.";
            case ClickSignCodes::TOKEN_AUSENTE: return "Token ausente.";
            default: return "Mensagem de retorno desconhecida.";
        }
    }
}
