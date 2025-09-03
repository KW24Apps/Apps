<?php
namespace Services\ClickSign;

require_once __DIR__ . '/loader.php';

use Helpers\BitrixDealHelper;
use Helpers\ClickSignHelper;
use Helpers\LogHelper;
use Helpers\UtilHelpers;
use Helpers\BitrixHelper;
use Enums\ClickSignCodes;
use Repositories\ClickSignDAO;
use PDOException;

class GerarAssinaturaService
{
    public static function gerarAssinatura(array $params): array
    {
        LogHelper::logClickSign("Início do processo de assinatura", 'service');

        $entityId = $params['spa'] ?? $params['entityId'] ?? null;
        $id = $params['deal'] ?? $params['id'] ?? null;

        $configExtra = $GLOBALS['ACESSO_AUTENTICADO']['config_extra'] ?? null;
        $configJson = $configExtra ? json_decode($configExtra, true) : [];
        $spaKey = 'SPA_' . $entityId;
        $fields = $configJson[$spaKey]['campos'] ?? [];
        $tokenClicksign = $configJson[$spaKey]['clicksign_token'] ?? null;

        // Extrai campo_retorno de fields (config_extra), se disponível, ou dos params originais como fallback
        $campoRetornoBitrix = $fields['retorno'] ?? $params['retorno'] ?? $params['campo_retorno'] ?? null;
        $paramsForUpdate = ['campo_retorno' => $campoRetornoBitrix];
        LogHelper::logClickSign("GerarAssinaturaService::gerarAssinatura - campoRetornoBitrix para atualização: " . ($campoRetornoBitrix ?? 'N/A'), 'debug');

        if (empty($id) || empty($entityId)) {
            $codigoRetorno = ClickSignCodes::PARAMS_AUSENTES;
            $constantName = array_search($codigoRetorno, (new \ReflectionClass(ClickSignCodes::class))->getConstants());
            $mensagem = $codigoRetorno . "-" . ($constantName ?: "MENSAGEM_DESCONHECIDA");
            LogHelper::logClickSign($mensagem . " - Parâmetros obrigatórios ausentes.", 'service');
            UtilService::atualizarRetornoBitrix($paramsForUpdate, $entityId, $id, false, null, $codigoRetorno, " - Parâmetros obrigatórios ausentes.");
            return ['success' => false, 'mensagem' => $mensagem];
        }

        // Verificar se já existe uma assinatura ativa para este deal_id e spa
        if (ClickSignDAO::verificarAssinaturaAtivaPorDealId($id, $entityId)) {
            $codigoRetorno = ClickSignCodes::ASSINATURA_JA_EM_ANDAMENTO;
            $constantName = array_search($codigoRetorno, (new \ReflectionClass(ClickSignCodes::class))->getConstants());
            $mensagem = $codigoRetorno . "-" . ($constantName ?: "MENSAGEM_DESCONHECIDA");
            LogHelper::logClickSign($mensagem . " para este Deal.", 'service');
            UtilService::atualizarRetornoBitrix($paramsForUpdate, $entityId, $id, false, null, $codigoRetorno, " para este Deal.");
            return ['success' => false, 'mensagem' => $mensagem];
        }

        if (!$tokenClicksign) {
            $codigoRetorno = ClickSignCodes::ACESSO_NAO_AUTORIZADO;
            $constantName = array_search($codigoRetorno, (new \ReflectionClass(ClickSignCodes::class))->getConstants());
            $mensagem = $codigoRetorno . "-" . ($constantName ?: "MENSAGEM_DESCONHECIDA");
            LogHelper::logClickSign($mensagem . " - Acesso não autorizado ou incompleto.", 'service');
            UtilService::atualizarRetornoBitrix($paramsForUpdate, $entityId, $id, false, null, $codigoRetorno, " - Acesso não autorizado ou incompleto.");
            return ['success' => false, 'mensagem' => $mensagem];
        }

        $registro = BitrixDealHelper::consultarDeal($entityId, $id, $fields);
        $dados = $registro['result'] ?? [];

        if (!isset($dados['id'])) {
            $codigoRetorno = ClickSignCodes::DEAL_NAO_ENCONTRADO;
            $constantName = array_search($codigoRetorno, (new \ReflectionClass(ClickSignCodes::class))->getConstants());
            $mensagem = $codigoRetorno . "-" . ($constantName ?: "MENSAGEM_DESCONHECIDA");
            LogHelper::logClickSign("ERRO - " . $mensagem . " | ID: $id", 'service');
            UtilService::atualizarRetornoBitrix($paramsForUpdate, $entityId, $id, false, null, $codigoRetorno, " | ID: $id");
            return ['success' => false, 'error' => $mensagem];
        }

        $camposNecessarios = ['contratante', 'contratada', 'testemunhas', 'data', 'arquivoaserassinado', 'arquivoassinado', 'idclicksign', 'retorno'];
        $mapCampos = [];
        foreach ($camposNecessarios as $campo) {
            if (!empty($params[$campo])) {
                $normalizado = BitrixHelper::formatarCampos([$params[$campo] => null]);
                $mapCampos[$campo] = array_key_first($normalizado);
            }
        }

        $errosValidacao = self::validarCamposEssenciais($dados, $mapCampos);
        if (!empty($errosValidacao)) {
            $detalhesErro = implode(', ', $errosValidacao);
            $codigoRetorno = ClickSignCodes::CAMPOS_INVALIDOS;
            $constantName = array_search($codigoRetorno, (new \ReflectionClass(ClickSignCodes::class))->getConstants());
            $mensagemErro = $codigoRetorno . "-" . ($constantName ?: "MENSAGEM_DESCONHECIDA");
            LogHelper::logClickSign("ERRO - " . $mensagemErro . ": $detalhesErro", 'service');
            UtilService::atualizarRetornoBitrix($paramsForUpdate, $entityId, $id, false, null, $codigoRetorno, ": $detalhesErro");
            return ['success' => false, 'error' => $mensagemErro];
        }

        $idsContratante = isset($mapCampos['contratante']) ? ($dados[$mapCampos['contratante']]['valor'] ?? null) : null;
        $idsContratada = isset($mapCampos['contratada']) ? ($dados[$mapCampos['contratada']]['valor'] ?? null) : null;
        $idsTestemunhas = isset($mapCampos['testemunhas']) ? ($dados[$mapCampos['testemunhas']]['valor'] ?? null) : null;
        $dataAssinatura = isset($mapCampos['data']) ? ($dados[$mapCampos['data']]['valor'] ?? null) : null;

        if ($dataAssinatura) {
            $dataAssinatura = substr($dataAssinatura, 0, 10);
            if (strpos($dataAssinatura, '/') !== false) {
                $partes = explode('/', $dataAssinatura);
                $dataAssinatura = "{$partes[2]}-{$partes[1]}-{$partes[0]}";
            }
        }

        $resultadoSignatarios = UtilService::processarSignatarios($idsContratante, $idsContratada, $idsTestemunhas);
        if (!$resultadoSignatarios['success']) {
            $codigoRetorno = $resultadoSignatarios['codigo'] ?? ClickSignCodes::DADOS_SIGNATARIO_FALTANTES;
            $constantName = array_search($codigoRetorno, (new \ReflectionClass(ClickSignCodes::class))->getConstants());
            $mensagem = $codigoRetorno . "-" . ($constantName ?: "MENSAGEM_DESCONHECIDA");
            LogHelper::logClickSign("Erro: " . $mensagem . " - " . ($resultadoSignatarios['mensagem'] ?? ""), 'service');
            UtilService::atualizarRetornoBitrix($paramsForUpdate, $entityId, $id, false, null, $codigoRetorno, " - " . ($resultadoSignatarios['mensagem'] ?? ""));
            return ['success' => false, 'mensagem' => $mensagem];
        }
        $signatarios = $resultadoSignatarios['signatarios'];
        $todosSignatariosParaJson = $resultadoSignatarios['todosSignatariosParaJson'];
        $qtdSignatarios = $resultadoSignatarios['qtdSignatarios'];

        LogHelper::logClickSign("Signatários validados | Total: $qtdSignatarios", 'service');

        $campoArquivo = isset($mapCampos['arquivoaserassinado']) && isset($dados[$mapCampos['arquivoaserassinado']]) ? $dados[$mapCampos['arquivoaserassinado']] : null;
        $arquivoConvertido = self::processarArquivo($campoArquivo);
        if (!$arquivoConvertido['success']) {
            $codigoRetorno = $arquivoConvertido['codigo'] ?? ClickSignCodes::ERRO_CONVERTER_ARQUIVO;
            $constantName = array_search($codigoRetorno, (new \ReflectionClass(ClickSignCodes::class))->getConstants());
            $mensagem = $codigoRetorno . "-" . ($constantName ?: "MENSAGEM_DESCONHECIDA");
            LogHelper::logClickSign($mensagem . " - " . ($arquivoConvertido['mensagem'] ?? ""), 'service');
            UtilService::atualizarRetornoBitrix($paramsForUpdate, $entityId, $id, false, null, $codigoRetorno, " - " . ($arquivoConvertido['mensagem'] ?? ""));
            return ['success' => false, 'mensagem' => $mensagem];
        }

        $payloadClickSign = [
            'document' => [
                'content_base64' => $arquivoConvertido['data']['base64'],
                'name'           => $arquivoConvertido['data']['nome'],
                'path'           => '/' . $arquivoConvertido['data']['nome'],
                'deadline_at'    => $dataAssinatura
            ]
        ];

        $retornoClickSign = ClickSignHelper::criarDocumento($payloadClickSign);

        if (!isset($retornoClickSign['document']['key'])) {
            $codigoRetorno = ClickSignCodes::FALHA_VINCULO_SIGNATARIOS; // Usando um código genérico para falha na criação do documento
            $constantName = array_search($codigoRetorno, (new \ReflectionClass(ClickSignCodes::class))->getConstants());
            $mensagem = $codigoRetorno . "-" . ($constantName ?: "MENSAGEM_DESCONHECIDA");
            LogHelper::logClickSign($mensagem . " - Erro ao criar documento na ClickSign.", 'service');
            UtilService::atualizarRetornoBitrix($paramsForUpdate, $entityId, $id, false, null, $codigoRetorno, " - Erro ao criar documento na ClickSign.");
            return ['success' => false, 'mensagem' => $mensagem];
        }
        
        $documentKey = $retornoClickSign['document']['key'];
        LogHelper::logClickSign("Documento criado na ClickSign | ID: $documentKey", 'service');

        $resultadoVinculo = UtilService::vincularSignatarios($documentKey, $signatarios);
        if (!$resultadoVinculo['success']) {
            $codigoRetorno = ClickSignCodes::FALHA_VINCULO_SIGNATARIOS;
            $constantName = array_search($codigoRetorno, (new \ReflectionClass(ClickSignCodes::class))->getConstants());
            $mensagem = $codigoRetorno . "-" . ($constantName ?: "MENSAGEM_DESCONHECIDA");
            UtilService::atualizarRetornoBitrix($paramsForUpdate, $entityId, $id, false, $documentKey, $codigoRetorno, " - Documento criado, mas houve falha em um ou mais vínculos de signatários.");
            return ['success' => false, 'mensagem' => $mensagem, 'document_key' => $documentKey, 'qtd_signatarios' => $qtdSignatarios, 'qtd_vinculos' => $resultadoVinculo['qtdVinculos']];
        }

        $clienteId = $GLOBALS['ACESSO_AUTENTICADO']['cliente_id'] ?? null;
        $webhookBitrix = $GLOBALS['ACESSO_AUTENTICADO']['webhook_bitrix'] ?? null;
        $secretClicksign = $configJson[$spaKey]['clicksign_secret'] ?? null;

        // Construir o JSON consolidado para dados_conexao
        $consolidatedDadosConexao = [
            'webhook_bitrix' => $webhookBitrix,
            'clicksign_token' => $tokenClicksign,
            'clicksign_secret' => $secretClicksign,
            'campos' => $fields, // Inclui todos os mapeamentos de campos
            'deal_id' => $id,
            'spa' => $entityId,
            'etapa_concluida' => $params['EtapaConcluido'] ?? null,
            'signatarios_detalhes' => [
                'todos_signatarios' => array_map(function($signer) use ($resultadoVinculo) {
                    $bitrixId = $signer['id'];
                    $clicksignKey = null;
                    foreach ($resultadoVinculo['mapaIds'] as $vinculo) {
                        if ($vinculo['id_bitrix'] == $bitrixId) {
                            $clicksignKey = $vinculo['key_clicksign'];
                            break;
                        }
                    }
                    $signer['key_clicksign'] = $clicksignKey;
                    return $signer;
                }, $todosSignatariosParaJson)
            ]
        ];

        $dadosRegistro = [
            'document_key' => $documentKey,
            'cliente_id' => $clienteId,
            'dados_conexao' => json_encode($consolidatedDadosConexao, JSON_UNESCAPED_UNICODE)
        ];

        $gravado = self::registrarAssinaturaComRetry($dadosRegistro);

        if ($gravado) {
            // A função atualizarCamposSignatariosBitrix precisará ser ajustada para ler do JSON
            self::atualizarCamposSignatariosBitrix($consolidatedDadosConexao, $entityId, $id, $todosSignatariosParaJson);
            $codigoRetorno = ClickSignCodes::DOCUMENTO_ENVIADO;
            $constantName = array_search($codigoRetorno, (new \ReflectionClass(ClickSignCodes::class))->getConstants());
            $mensagem = $codigoRetorno . "-" . ($constantName ?: "MENSAGEM_DESCONHECIDA");
            UtilService::atualizarRetornoBitrix($paramsForUpdate, $entityId, $id, true, $documentKey, $codigoRetorno, null);
            LogHelper::logClickSign($mensagem, 'service');
            return ['success' => true, 'mensagem' => $mensagem, 'document_key' => $documentKey, 'qtd_signatarios' => $qtdSignatarios, 'qtd_vinculos' => $resultadoVinculo['qtdVinculos']];
        } else {
            $codigoRetorno = ClickSignCodes::FALHA_GRAVAR_ASSINATURA_BD;
            $constantName = array_search($codigoRetorno, (new \ReflectionClass(ClickSignCodes::class))->getConstants());
            $mensagemErro = $codigoRetorno . "-" . ($constantName ?: "MENSAGEM_DESCONHECIDA");
            UtilService::atualizarRetornoBitrix($paramsForUpdate, $entityId, $id, false, null, $codigoRetorno, " - Erro ao registrar assinatura no banco de dados. AVISO: Os retornos automáticos não funcionarão.");
            LogHelper::logClickSign($mensagemErro . " - Erro ao registrar assinatura no banco de dados. AVISO: Os retornos automáticos não funcionarão.", 'service');
            return ['success' => false, 'mensagem' => $mensagemErro, 'document_key' => $documentKey];
        }
    }

    private static function validarCamposEssenciais(array $dados, array $mapCampos): array
    {
        $erros = [];
        if (empty($dados[$mapCampos['arquivoaserassinado']]['valor'])) {
            $erros[] = 'Arquivo a ser assinado é obrigatório';
        }
        if (empty($dados[$mapCampos['data']]['valor'])) {
            $erros[] = 'Data limite de assinatura é obrigatória';
        } else {
            $dataLimite = substr($dados[$mapCampos['data']]['valor'], 0, 10);
            if ($dataLimite <= date('Y-m-d')) {
                $erros[] = 'Data limite deve ser posterior à data atual';
            }
        }
        $temSignatario = !empty($dados[$mapCampos['contratante']]['valor']) || !empty($dados[$mapCampos['contratada']]['valor']) || !empty($dados[$mapCampos['testemunhas']]['valor']);
        if (!$temSignatario) {
            $erros[] = 'Pelo menos um signatário é obrigatório';
        }
        return $erros;
    }

    private static function processarArquivo($campoArquivo): array
    {
        $urlMachine = $campoArquivo['valor'][0]['urlMachine'] ?? null;
        if (!$urlMachine) {
            return ['success' => false, 'mensagem' => ClickSignCodes::ERRO_CONVERTER_ARQUIVO . ' - URL do arquivo não encontrada.'];
        }
        $arquivoConvertido = UtilHelpers::baixarArquivoBase64(['urlMachine' => $urlMachine]);
        if (!$arquivoConvertido) {
            return ['success' => false, 'mensagem' => ClickSignCodes::ERRO_CONVERTER_ARQUIVO . ' - Erro ao converter o arquivo.'];
        }
        $extensoesPermitidas = ['pdf', 'docx', 'doc'];
        if (!in_array(strtolower($arquivoConvertido['extensao']), $extensoesPermitidas)) {
            return ['success' => false, 'mensagem' => ClickSignCodes::ERRO_CONVERTER_ARQUIVO . ' - Tipo de arquivo inválido.'];
        }
        return ['success' => true, 'data' => $arquivoConvertido];
    }

    private static function registrarAssinaturaComRetry(array $dados): bool
    {
        $tentativas = 0;
        $maxTentativas = 3;
        while ($tentativas < $maxTentativas) {
            try {
                // Filtra e passa apenas os parâmetros esperados pelo DAO
                $dadosParaSalvar = [
                    'document_key'               => $dados['document_key'],
                    'cliente_id'                 => $dados['cliente_id'],
                    'dados_conexao'              => $dados['dados_conexao'] ?? null
                ];

                if (ClickSignDAO::registrarAssinaturaClicksign($dadosParaSalvar)) {
                    return true;
                }
            } catch (PDOException $e) {
                // O erro já é logado no DAO
            }
            $tentativas++;
            if ($tentativas < $maxTentativas) sleep(1);
        }
        return false;
    }

    private static function atualizarCamposSignatariosBitrix(array $consolidatedDadosConexao, string $entityId, string $id, array $todosSignatariosParaJson)
    {
        $campos = $consolidatedDadosConexao['campos'] ?? [];
        $campoSignatariosAssinar = $campos['signatarios_assinar'] ?? null;
        $campoSignatariosAssinaram = $campos['signatarios_assinaram'] ?? null;
        
        if ($campoSignatariosAssinar && $campoSignatariosAssinaram) {
            $idsSignatarios = array_column($todosSignatariosParaJson, 'id');
            $fieldsUpdate = [
                $campoSignatariosAssinar => $idsSignatarios,
                $campoSignatariosAssinaram => ''
            ];
            BitrixDealHelper::editarDeal($entityId, $id, $fieldsUpdate);
        }
    }
}
