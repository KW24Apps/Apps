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

        if (empty($id) || empty($entityId)) {
            $mensagem = ClickSignCodes::PARAMS_AUSENTES . " - Parâmetros obrigatórios ausentes.";
            LogHelper::logClickSign($mensagem, 'service');
            return ['success' => false, 'mensagem' => $mensagem];
        }

        $configExtra = $GLOBALS['ACESSO_AUTENTICADO']['config_extra'] ?? null;
        $configJson = $configExtra ? json_decode($configExtra, true) : [];
        $spaKey = 'SPA_' . $entityId;
        $fields = $configJson[$spaKey]['campos'] ?? [];
        $tokenClicksign = $configJson[$spaKey]['clicksign_token'] ?? null;

        $params = array_merge($params, $fields);

        if (!$tokenClicksign) {
            $mensagem = ClickSignCodes::ACESSO_NAO_AUTORIZADO . " - Acesso não autorizado ou incompleto.";
            LogHelper::logClickSign($mensagem, 'service');
            UtilService::atualizarRetornoBitrix($params, $entityId, $id, false, null, $mensagem);
            return ['success' => false, 'mensagem' => $mensagem];
        }

        $registro = BitrixDealHelper::consultarDeal($entityId, $id, $fields);
        $dados = $registro['result'] ?? [];

        if (!isset($dados['id'])) {
            $mensagem = ClickSignCodes::DEAL_NAO_ENCONTRADO . " - Deal não encontrado | ID: $id";
            LogHelper::logClickSign("ERRO - $mensagem", 'service');
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
            $mensagemErro = ClickSignCodes::CAMPOS_INVALIDOS . " - Campos obrigatórios ausentes ou inválidos: $detalhesErro";
            LogHelper::logClickSign("ERRO - $mensagemErro", 'service');
            UtilService::atualizarRetornoBitrix($params, $entityId, $id, false, null, $mensagemErro);
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
            LogHelper::logClickSign("Erro: " . $resultadoSignatarios['mensagem'], 'service');
            UtilService::atualizarRetornoBitrix($params, $entityId, $id, false, null, $resultadoSignatarios['mensagem']);
            return $resultadoSignatarios;
        }
        $signatarios = $resultadoSignatarios['signatarios'];
        $todosSignatariosParaJson = $resultadoSignatarios['todosSignatariosParaJson'];
        $qtdSignatarios = $resultadoSignatarios['qtdSignatarios'];

        LogHelper::logClickSign("Signatários validados | Total: $qtdSignatarios", 'service');

        $campoArquivo = isset($mapCampos['arquivoaserassinado']) && isset($dados[$mapCampos['arquivoaserassinado']]) ? $dados[$mapCampos['arquivoaserassinado']] : null;
        $arquivoConvertido = self::processarArquivo($campoArquivo);
        if (!$arquivoConvertido['success']) {
            LogHelper::logClickSign($arquivoConvertido['mensagem'], 'service');
            UtilService::atualizarRetornoBitrix($params, $entityId, $id, false, null, $arquivoConvertido['mensagem']);
            return $arquivoConvertido;
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
            $mensagem = ClickSignCodes::FALHA_VINCULO_SIGNATARIOS . " - Erro ao criar documento na ClickSign.";
            LogHelper::logClickSign($mensagem, 'service');
            return ['success' => false, 'mensagem' => $mensagem];
        }
        
        $documentKey = $retornoClickSign['document']['key'];
        LogHelper::logClickSign("Documento criado na ClickSign | ID: $documentKey", 'service');

        $resultadoVinculo = UtilService::vincularSignatarios($documentKey, $signatarios);
        if (!$resultadoVinculo['success']) {
            $mensagem = ClickSignCodes::FALHA_VINCULO_SIGNATARIOS . " - Documento criado, mas houve falha em um ou mais vínculos de signatários.";
            return ['success' => false, 'mensagem' => $mensagem, 'document_key' => $documentKey, 'qtd_signatarios' => $qtdSignatarios, 'qtd_vinculos' => $resultadoVinculo['qtdVinculos']];
        }

        $clienteId = $GLOBALS['ACESSO_AUTENTICADO']['cliente_id'] ?? null;
        $webhookBitrix = $GLOBALS['ACESSO_AUTENTICADO']['webhook_bitrix'] ?? null;
        $secretClicksign = $configJson[$spaKey]['clicksign_secret'] ?? null;

        $dadosConexao = ['webhook_bitrix' => $webhookBitrix, 'clicksign_token' => $tokenClicksign, 'clicksign_secret' => $secretClicksign];

        $dadosRegistro = array_merge($params, [
            'document_key' => $documentKey,
            'cliente_id' => $clienteId,
            'deal_id' => $id,
            'spa' => $entityId,
            'Signatarios' => json_encode($todosSignatariosParaJson, JSON_UNESCAPED_UNICODE),
            'etapa_concluida' => $params['EtapaConcluido'] ?? null,
            'dados_conexao' => json_encode($dadosConexao, JSON_UNESCAPED_UNICODE),
            'ids_signatarios' => json_encode($resultadoVinculo['mapaIds'], JSON_UNESCAPED_UNICODE)
        ]);

        $gravado = self::registrarAssinaturaComRetry($dadosRegistro);

        if ($gravado) {
            self::atualizarCamposSignatariosBitrix($params, $entityId, $id, $todosSignatariosParaJson);
            $mensagem = ClickSignCodes::DOCUMENTO_ENVIADO . " - Documento enviado para assinatura";
            UtilService::atualizarRetornoBitrix($params, $entityId, $id, true, $documentKey, $mensagem);
            LogHelper::logClickSign($mensagem . " e dados atualizados no Bitrix com sucesso", 'service');
            return ['success' => true, 'mensagem' => $mensagem, 'document_key' => $documentKey, 'qtd_signatarios' => $qtdSignatarios, 'qtd_vinculos' => $resultadoVinculo['qtdVinculos']];
        } else {
            $mensagemErro = ClickSignCodes::FALHA_GRAVAR_ASSINATURA_BD . " - Erro ao registrar assinatura no banco de dados. AVISO: Os retornos automáticos não funcionarão.";
            UtilService::atualizarRetornoBitrix($params, $entityId, $id, false, null, $mensagemErro);
            LogHelper::logClickSign("Documento finalizado, mas erro ao gravar controle de assinatura", 'service');
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
                    'deal_id'                    => $dados['deal_id'],
                    'spa'                        => $dados['spa'],
                    'Signatarios'                => $dados['Signatarios'],
                    'campo_contratante'          => $dados['contratante'] ?? null,
                    'campo_contratada'           => $dados['contratada'] ?? null,
                    'campo_testemunhas'          => $dados['testemunhas'] ?? null,
                    'campo_data'                 => $dados['data'] ?? null,
                    'campo_arquivoaserassinado'  => $dados['arquivoaserassinado'] ?? null,
                    'campo_arquivoassinado'      => $dados['arquivoassinado'] ?? null,
                    'campo_idclicksign'          => $dados['idclicksign'] ?? null,
                    'campo_retorno'              => $dados['retorno'] ?? null,
                    'etapa_concluida'            => $dados['etapa_concluida'] ?? null,
                    'dados_conexao'              => $dados['dados_conexao'] ?? null,
                    'ids_signatarios'            => $dados['ids_signatarios'] ?? null
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

    private static function atualizarCamposSignatariosBitrix(array $params, string $entityId, string $id, array $todosSignatariosParaJson)
    {
        $campoSignatariosAssinar = $params['signatarios_assinar'] ?? null;
        $campoSignatariosAssinaram = $params['signatarios_assinaram'] ?? null;
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
