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

    public static function atualizarRetornoBitrix($params, $spa, $dealId, $sucesso, $documentKey, $mensagemCustomizada = null)
    {
        $campoRetorno = $params['retorno'] ?? $params['campo_retorno'] ?? null;
        $campoIdClickSign = $params['idclicksign'] ?? $params['campo_idclicksign'] ?? null;

        $mensagemRetorno = $sucesso ?
            ($mensagemCustomizada ?? "Documento enviado para assinatura") :
            ($mensagemCustomizada ?? "Erro no envio do documento para assinatura");

        $fields = [];
        if ($campoRetorno) {
            $fields[$campoRetorno] = $mensagemRetorno;
        }

        if ($sucesso && $campoIdClickSign && $documentKey) {
            $fields[$campoIdClickSign] = $documentKey;
        }

        if (!empty($fields)) {
            BitrixDealHelper::editarDeal($spa, $dealId, $fields);
        }

        $comentario = "Retorno ClickSign: " . $mensagemRetorno;
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
}
