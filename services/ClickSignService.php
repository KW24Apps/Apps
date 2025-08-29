<?php
namespace Services;

require_once __DIR__ . '/../helpers/BitrixDealHelper.php';
require_once __DIR__ . '/../helpers/BitrixContactHelper.php';
require_once __DIR__ . '/../helpers/ClickSignHelper.php';
require_once __DIR__ . '/../helpers/LogHelper.php';
require_once __DIR__ . '/../Repositories/AplicacaoAcessoDAO.php';
require_once __DIR__ . '/../helpers/UtilHelpers.php';
require_once __DIR__ . '/../helpers/BitrixHelper.php';

use Helpers\BitrixDealHelper;
use Helpers\BitrixContactHelper;
use Helpers\ClickSignHelper;
use Helpers\LogHelper;
use Repositories\AplicacaoAcessoDAO;
use Helpers\BitrixHelper;
use Helpers\UtilHelpers;
use PDOException;

class ClickSignService
{
    // Orquestra a geração de uma assinatura na ClickSign.
    public static function gerarAssinatura(array $params): array
    {
        LogHelper::logClickSign("Início do processo de assinatura", 'service');

        $entityId = $params['spa'] ?? $params['entityId'] ?? null;
        $id = $params['deal'] ?? $params['id'] ?? null;

        if (empty($id) || empty($entityId)) {
            LogHelper::logClickSign("Parâmetros obrigatórios ausentes", 'service');
            return ['success' => false, 'mensagem' => 'Parâmetros obrigatórios ausentes.'];
        }

        $configExtra = $GLOBALS['ACESSO_AUTENTICADO']['config_extra'] ?? null;
        $configJson = $configExtra ? json_decode($configExtra, true) : [];
        $spaKey = 'SPA_' . $entityId;
        $fields = $configJson[$spaKey]['campos'] ?? [];
        $tokenClicksign = $configJson[$spaKey]['clicksign_token'] ?? null;

        $params = array_merge($params, $fields);

        if (!$tokenClicksign) {
            LogHelper::logClickSign("Token ClickSign ausente", 'service');
            self::atualizarRetornoBitrix($params, $entityId, $id, false, null, 'Acesso não autorizado ou incompleto');
            return ['success' => false, 'mensagem' => 'Acesso não autorizado ou incompleto.'];
        }

        $registro = BitrixDealHelper::consultarDeal($entityId, $id, $fields);
        $dados = $registro['result'] ?? [];

        if (!isset($dados['id']) || (is_array($dados['id']) && (!isset($dados['id']['valor']) || $dados['id']['valor'] === null))) {
            LogHelper::logClickSign("ERRO - Deal não encontrado | ID: $entityId", 'service');
            return ['success' => false, 'error' => 'Deal não encontrado'];
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
            $mensagemErro = 'Campos obrigatórios ausentes ou inválidos: ' . implode(', ', $errosValidacao);
            LogHelper::logClickSign("ERRO - $mensagemErro", 'service');
            self::atualizarRetornoBitrix($params, $entityId, $id, false, null, $mensagemErro);
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

        $resultadoSignatarios = self::processarSignatarios($idsContratante, $idsContratada, $idsTestemunhas);
        if (!$resultadoSignatarios['success']) {
            LogHelper::logClickSign("Erro: " . $resultadoSignatarios['mensagem'], 'service');
            self::atualizarRetornoBitrix($params, $entityId, $id, false, null, $resultadoSignatarios['mensagem']);
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
            self::atualizarRetornoBitrix($params, $entityId, $id, false, null, $arquivoConvertido['mensagem']);
            return $arquivoConvertido;
        }

        $payloadClickSign = [
            'document' => [
                'content_base64' => $arquivoConvertido['data']['base64'],
                'name'           => $arquivoConvertido['data']['nome'],
                'path'           => '/' . $arquivoConvertido['data']['nome'],
                'content_type'   => $arquivoConvertido['data']['mime'],
                'remind_interval'    => 2,
                'deadline_at'    => $dataAssinatura
            ]
        ];

        $retornoClickSign = ClickSignHelper::criarDocumento($payloadClickSign);

        if (!isset($retornoClickSign['document']['key'])) {
            LogHelper::logClickSign("Erro ao criar documento na ClickSign", 'service');
            return ['success' => false, 'mensagem' => 'Erro ao criar documento na ClickSign.'];
        }
        
        $documentKey = $retornoClickSign['document']['key'];
        LogHelper::logClickSign("Documento criado na ClickSign | ID: $documentKey", 'service');

        $resultadoVinculo = self::vincularSignatarios($documentKey, $signatarios);
        if (!$resultadoVinculo['success']) {
            return [
                'success' => false,
                'mensagem' => 'Documento criado, mas houve falha em um ou mais vínculos de signatários.',
                'document_key' => $documentKey,
                'qtd_signatarios' => $qtdSignatarios,
                'qtd_vinculos' => $resultadoVinculo['qtdVinculos']
            ];
        }

        $clienteId = $GLOBALS['ACESSO_AUTENTICADO']['cliente_id'] ?? null;
        $dadosRegistro = array_merge($params, [
            'document_key' => $documentKey,
            'cliente_id' => $clienteId,
            'deal_id' => $id,
            'spa' => $entityId,
            'Signatarios' => json_encode($todosSignatariosParaJson, JSON_UNESCAPED_UNICODE),
            'etapa_concluida' => $params['EtapaConcluido'] ?? null
        ]);

        $gravado = self::registrarAssinaturaComRetry($dadosRegistro);

        if ($gravado) {
            self::atualizarCamposSignatariosBitrix($params, $entityId, $id, $todosSignatariosParaJson);
            self::atualizarRetornoBitrix($params, $entityId, $id, true, $documentKey, 'Documento enviado para assinatura');
            LogHelper::logClickSign("Documento enviado para assinatura e dados atualizados no Bitrix com sucesso", 'service');
            return [
                'success' => true,
                'mensagem' => 'Documento enviado para assinatura',
                'document_key' => $documentKey,
                'qtd_signatarios' => $qtdSignatarios,
                'qtd_vinculos' => $resultadoVinculo['qtdVinculos']
            ];
        } else {
            $mensagemErro = "Erro ao registrar assinatura no banco de dados. AVISO: Os retornos automáticos (quem assinou, documento finalizado) não funcionarão. O acompanhamento deverá ser manual. Entre em contato com o suporte de TI.";
            self::atualizarRetornoBitrix($params, $entityId, $id, false, null, $mensagemErro);
            LogHelper::logClickSign("Documento finalizado, mas erro ao gravar controle de assinatura", 'service');
            return [
                'success' => false,
                'mensagem' => 'Assinatura criada, mas falha ao gravar controle de assinatura.',
                'document_key' => $documentKey
            ];
        }
    }

    // Valida os campos essenciais do deal para a assinatura.
    private static function validarCamposEssenciais(array $dados, array $mapCampos): array
    {
        $erros = [];

        $arquivoParaAssinar = null;
        if (isset($mapCampos['arquivoaserassinado']) && isset($dados[$mapCampos['arquivoaserassinado']])) {
            $campoArquivo = $dados[$mapCampos['arquivoaserassinado']];
            if (isset($campoArquivo['valor']) && is_array($campoArquivo['valor']) && !empty($campoArquivo['valor'])) {
                $arquivoParaAssinar = $campoArquivo['valor'];
            }
        }
        if (empty($arquivoParaAssinar)) {
            $erros[] = 'Arquivo a ser assinado é obrigatório';
        }

        $dataLimite = null;
        if (isset($mapCampos['data']) && isset($dados[$mapCampos['data']])) {
            $dataLimite = $dados[$mapCampos['data']]['valor'] ?? null;
        }
        if (empty($dataLimite)) {
            $erros[] = 'Data limite de assinatura é obrigatória';
        } else {
            $dataLimiteFormatada = substr($dataLimite, 0, 10);
            $dataAtual = date('Y-m-d');
            if ($dataLimiteFormatada <= $dataAtual) {
                $erros[] = 'Data limite deve ser posterior à data atual';
            }
        }

        $temSignatario = false;
        $signatariosCampos = ['contratante', 'contratada', 'testemunhas'];
        foreach ($signatariosCampos as $campo) {
            if (isset($mapCampos[$campo]) && isset($dados[$mapCampos[$campo]])) {
                $valor = $dados[$mapCampos[$campo]]['valor'] ?? null;
                if (!empty($valor) && is_array($valor) && count($valor) > 0) {
                    $temSignatario = true;
                    break;
                }
            }
        }
        if (!$temSignatario) {
            $erros[] = 'Pelo menos um signatário deve estar configurado (contratante, contratada ou testemunha)';
        }

        return $erros;
    }

    // Consulta e formata os dados dos signatários a partir dos IDs do Bitrix.
    private static function processarSignatarios($idsContratante, $idsContratada, $idsTestemunhas): array
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
                    return ['success' => false, 'mensagem' => "Dados faltantes nos signatários ($papel)"];
                }

                $signatarioInfo = ['nome' => $nome, 'sobrenome' => $sobrenome, 'email' => $email];
                $signatarios[$papel][] = $signatarioInfo;
                $todosSignatariosParaJson[] = array_merge(['id' => $idContato], $signatarioInfo);
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

    // Baixa o arquivo do Bitrix, valida a extensão e o converte para base64.
    private static function processarArquivo($campoArquivo): array
    {
        $urlMachine = null;
        if (is_array($campoArquivo)) {
            if (isset($campoArquivo['valor'][0]['urlMachine'])) {
                $urlMachine = $campoArquivo['valor'][0]['urlMachine'];
            } elseif (isset($campoArquivo['valor'][0]['url'])) {
                $urlMachine = $campoArquivo['valor'][0]['url'];
            } elseif (isset($campoArquivo['urlMachine'])) {
                $urlMachine = $campoArquivo['urlMachine'];
            }
        }

        if (!$urlMachine) {
            return ['success' => false, 'mensagem' => 'URL do arquivo não encontrada.'];
        }

        $arquivoConvertido = UtilHelpers::baixarArquivoBase64(['urlMachine' => $urlMachine]);

        if (!$arquivoConvertido) {
            return ['success' => false, 'mensagem' => 'Erro ao converter o arquivo.'];
        }

        $extensoesPermitidas = ['pdf', 'docx', 'doc', 'png', 'jpg', 'jpeg'];
        $extensaoArquivo = strtolower($arquivoConvertido['extensao']);
        if (!in_array($extensaoArquivo, $extensoesPermitidas)) {
            $mensagemErro = 'Tipo de arquivo inválido. Apenas (' . implode(', ', $extensoesPermitidas) . ') são permitidos.';
            return ['success' => false, 'mensagem' => $mensagemErro];
        }

        return ['success' => true, 'data' => $arquivoConvertido];
    }

    // Cria os signatários na ClickSign e os vincula ao documento.
    private static function vincularSignatarios(string $documentKey, array $signatarios): array
    {
        $mapSignAs = ['contratante' => 'contractor', 'contratada' => 'contractee', 'testemunha' => 'witness'];
        $sucessoVinculo = true;
        $qtdVinculos = 0;

        foreach ($signatarios as $papel => $listaSignatarios) {
            foreach ($listaSignatarios as $signatario) {
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
        return ['success' => $sucessoVinculo, 'qtdVinculos' => $qtdVinculos];
    }

    // Grava os dados da assinatura no banco de dados local com múltiplas tentativas.
    private static function registrarAssinaturaComRetry(array $dados): bool
    {
        $tentativas = 0;
        $maxTentativas = 3;
        while ($tentativas < $maxTentativas) {
            try {
                AplicacaoAcessoDAO::registrarAssinaturaClicksign([
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
                    'etapa_concluida'            => $dados['etapa_concluida'] ?? null
                ]);
                return true;
            } catch (PDOException $e) {
                $tentativas++;
                if ($tentativas < $maxTentativas) sleep(10);
            }
        }
        return false;
    }

    // Atualiza os campos de signatários no deal do Bitrix.
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

    // Atualiza o campo de retorno e adiciona um comentário no deal do Bitrix.
    private static function atualizarRetornoBitrix($params, $spa, $dealId, $sucesso, $documentKey, $mensagemCustomizada = null)
    {
        $campoRetorno = $params['retorno'] ?? null;
        $campoIdClickSign = $params['idclicksign'] ?? null;

        $mensagemRetorno = $sucesso ?
            ($mensagemCustomizada ?? "Documento enviado para assinatura") :
            ($mensagemCustomizada ?? "Erro no envio do documento para assinatura");

        $fields = [];
        $mensagemFinalTrigger = 'Documento assinado e arquivo anexado com sucesso.';

        if ($campoRetorno && (!$sucesso || $mensagemCustomizada === $mensagemFinalTrigger)) {
            $fields[$campoRetorno] = ($mensagemCustomizada === $mensagemFinalTrigger) ? 'CS405' : $mensagemRetorno;
        }

        if ($sucesso && $campoIdClickSign && $documentKey) {
            $fields[$campoIdClickSign] = $documentKey;
        }

        if (!empty($fields)) {
            $response = BitrixDealHelper::editarDeal($spa, $dealId, $fields);
            if (!isset($response['status']) || $response['status'] !== 'sucesso') {
                LogHelper::logClickSign("ERRO ao editar deal em atualizarRetornoBitrix | spa: $spa | dealId: $dealId | fields: " . json_encode($fields) . " | response: " . json_encode($response), 'atualizarRetornoBitrix');
            }
        }

        $comentario = "Retorno ClickSign: " . $mensagemRetorno;
        if ($documentKey) {
            $comentario .= "\nDocumento ID: " . $documentKey;
        }
        BitrixDealHelper::adicionarComentarioDeal($spa, $dealId, $comentario);
    }

    // Processa um webhook de entrada da ClickSign.
    public static function processarWebhook(array $requestData, string $rawBody, ?string $headerSignature): array
    {
        $documentKey = $requestData['document']['key'] ?? null;
        if (empty($documentKey)) {
            LogHelper::logClickSign("Parâmetros obrigatórios ausentes | DocumentKey: $documentKey", 'service');
            return ['success' => false, 'mensagem' => 'Parâmetros obrigatórios ausentes.'];
        }

        $dadosAssinatura = AplicacaoAcessoDAO::obterAssinaturaClickSign($documentKey);
        if (!$dadosAssinatura) {
            LogHelper::logClickSign("Documento não encontrado | DocumentKey: $documentKey", 'service');
            return ['success' => false, 'mensagem' => 'Documento não encontrado.'];
        }

        $configExtra = $GLOBALS['ACESSO_AUTENTICADO']['config_extra'] ?? null;
        $configJson = $configExtra ? json_decode($configExtra, true) : [];
        $spa = $dadosAssinatura['spa'] ?? null;
        $spaKey = 'SPA_' . $spa;
        $secret = $configJson[$spaKey]['clicksign_secret'] ?? null;
        $token = $configJson[$spaKey]['clicksign_token'] ?? null;

        if (empty($secret) || empty($token)) {
            LogHelper::logClickSign("ERRO: Token ou Secret não configurados para a SPA: $spaKey", 'service');
            return ['success' => false, 'mensagem' => 'Credenciais de API não configuradas.'];
        }

        if (!ClickSignHelper::validarHmac($rawBody, $secret, $headerSignature)) {
            LogHelper::logClickSign("Assinatura HMAC inválida", 'service');
            return ['success' => false, 'mensagem' => 'Assinatura HMAC inválida.'];
        }

        $evento = $requestData['event']['name'] ?? null;
        LogHelper::logClickSign("Webhook recebido: $evento | Documento: $documentKey", 'service');

        switch ($evento) {
            case 'sign':
                $assinante = $requestData['event']['data']['signer']['email'] ?? null;
                if (!empty($dadosAssinatura['assinatura_processada']) && strpos($dadosAssinatura['assinatura_processada'], $assinante) !== false) {
                    return ['success' => true, 'mensagem' => 'Assinatura já processada.'];
                }
                AplicacaoAcessoDAO::salvarStatus($documentKey, null, ($dadosAssinatura['assinatura_processada'] ?? '') . ";" . $assinante);
                return self::assinaturaRealizada($requestData, $dadosAssinatura);

            case 'deadline':
            case 'cancel':
            case 'auto_close':
                if (!empty($dadosAssinatura['documento_fechado_processado'])) {
                    return ['success' => true, 'mensagem' => 'Evento de documento fechado já processado.'];
                }
                AplicacaoAcessoDAO::salvarStatus($documentKey, $evento, null, true);
                return self::documentoFechado($requestData, $dadosAssinatura);

            case 'document_closed':
                if (!empty($dadosAssinatura['documento_disponivel_processado'])) {
                    return ['success' => true, 'mensagem' => 'Documento já disponível e processado anteriormente.'];
                }
                AplicacaoAcessoDAO::salvarStatus($documentKey, null, null, null, true);
                return self::documentoDisponivel($requestData, $dadosAssinatura, $token);

            default:
                return ['success' => true, 'mensagem' => 'Evento recebido sem ação específica.'];
        }
    }

    // Trata o evento 'sign' do webhook, quando um signatário assina.
    private static function assinaturaRealizada(array $requestData, array $dadosAssinatura): array
    {
        $spa = $dadosAssinatura['spa'];
        $dealId = $dadosAssinatura['deal_id'];
        $documentKey = $dadosAssinatura['document_key'];
        $signer = $requestData['event']['data']['signer'] ?? null;

        if (!$signer) {
            LogHelper::logClickSign("ERRO: Dados do signatário não encontrados no evento", 'assinaturaRealizada');
            return ['success' => false, 'mensagem' => 'Dados do signatário não encontrados.'];
        }

        $nome  = $signer['name']  ?? '';
        $email = $signer['email'] ?? '';

        if (empty($nome) || empty($email)) {
            LogHelper::logClickSign("ERRO: Dados do signatário incompletos | nome: $nome | email: $email", 'assinaturaRealizada');
            return ['success' => false, 'mensagem' => 'Dados do signatário incompletos.'];
        }

        $configExtra = $GLOBALS['ACESSO_AUTENTICADO']['config_extra'] ?? null;
        $configJson = $configExtra ? json_decode($configExtra, true) : [];
        $spaKey = 'SPA_' . $spa;
        $campos = $configJson[$spaKey]['campos'] ?? [];
        $campoSignatariosAssinar = $campos['signatarios_assinar'] ?? null;
        $campoSignatariosAssinaram = $campos['signatarios_assinaram'] ?? null;

        if ($campoSignatariosAssinar && $campoSignatariosAssinaram) {
            $signatariosJson = $dadosAssinatura['Signatarios'] ?? '[]';
            $todosSignatarios = json_decode($signatariosJson, true);
            $assinaturasProcessadas = array_filter(explode(';', $dadosAssinatura['assinatura_processada'] ?? ''));
            $idsAssinaram = [];
            $idsTodos = array_column($todosSignatarios, 'id');

            foreach ($todosSignatarios as $s) {
                if (in_array($s['email'], $assinaturasProcessadas)) {
                    $idsAssinaram[] = $s['id'];
                }
            }
            
            $idsAAssinar = array_diff($idsTodos, $idsAssinaram);
            $fieldsUpdate = [
                $campoSignatariosAssinaram => array_values($idsAssinaram),
                $campoSignatariosAssinar => empty($idsAAssinar) ? '' : array_values($idsAAssinar)
            ];
            BitrixDealHelper::editarDeal($spa, $dealId, $fieldsUpdate);
        }

        $mensagem = "Assinatura feita por $nome - $email";
        self::atualizarRetornoBitrix($dadosAssinatura, $spa, $dealId, true, $documentKey, $mensagem);
        return ['success' => true, 'mensagem' => 'Assinatura processada e campos atualizados.'];
    }

    // Trata eventos de fechamento de documento (deadline, cancel, auto_close).
    private static function documentoFechado(array $requestData, array $dadosAssinatura): array
    {
        $evento = $requestData['event']['name'];
        if (in_array($evento, ['deadline', 'cancel'])) {
            $mensagem = $evento === 'deadline' ? 'Assinatura cancelada por prazo finalizado.' : 'Assinatura cancelada manualmente.';
            self::atualizarRetornoBitrix($dadosAssinatura, $dadosAssinatura['spa'], $dadosAssinatura['deal_id'], true, $dadosAssinatura['document_key'], $mensagem);
            return ['success' => true, 'mensagem' => "Evento $evento processado com atualização imediata no Bitrix."];
        }

        if ($evento === 'auto_close') {
            return ['success' => true, 'mensagem' => 'Evento auto_close salvo. Aguardando document_closed para baixar o arquivo.'];
        }

        LogHelper::logClickSign("Evento de fechamento não tratado: $evento", 'service');
        return ['success' => true, 'mensagem' => "Evento de fechamento $evento ignorado."];
    }

    // Trata o evento 'document_closed', quando o documento finalizado está disponível.
    private static function documentoDisponivel(array $requestData, array $dadosAssinatura, string $token): array
    {
        $spa = $dadosAssinatura['spa'];
        $dealId = $dadosAssinatura['deal_id'];
        $documentKey = $dadosAssinatura['document_key'];
        $campoArquivoAssinado = $dadosAssinatura['campo_arquivoassinado'];

        $statusClosed = AplicacaoAcessoDAO::obterAssinaturaClickSign($documentKey); // Re-consulta para garantir o status mais recente
        if (in_array($statusClosed['status_closed'] ?? '', ['deadline', 'cancel'])) {
            return ['success' => true, 'mensagem' => "Evento {$statusClosed['status_closed']} ignorado."];
        }

        if (($statusClosed['status_closed'] ?? '') !== 'auto_close') {
            return ['success' => true, 'mensagem' => 'StatusClosed não tratado.'];
        }

        $url = $requestData['document']['downloads']['signed_file_url'] ?? null;
        if (!$url) {
            LogHelper::logClickSign("ERRO: URL do arquivo assinado não encontrada no webhook.", 'documentoDisponivel');
            return ['success' => false, 'mensagem' => 'URL de download não encontrada no webhook.'];
        }

        if (empty($campoArquivoAssinado)) {
            LogHelper::logClickSign("AVISO: Campo arquivo assinado não configurado.", 'documentoDisponivel');
            self::atualizarRetornoBitrix($dadosAssinatura, $spa, $dealId, true, $documentKey, 'Documento assinado com sucesso.');
            return ['success' => true, 'mensagem' => 'Mensagem final enviada (sem anexo de arquivo).'];
        }

        $nomeArquivo = $requestData['document']['filename'] ?? "documento_assinado.pdf";
        $arquivoBase64 = UtilHelpers::baixarArquivoBase64(['urlMachine' => $url, 'name' => $nomeArquivo]);
        if (!$arquivoBase64) {
            LogHelper::logClickSign("ERRO: Erro ao baixar/converter arquivo.", 'documentoDisponivel');
            return ['success' => false, 'mensagem' => 'Falha ao converter o arquivo.'];
        }

        $arquivoParaBitrix = [['filename' => $arquivoBase64['nome'], 'data' => str_replace('data:' . $arquivoBase64['mime'] . ';base64,', '', $arquivoBase64['base64'])]];
        $fields = [$campoArquivoAssinado => $arquivoParaBitrix];

        $sucessoAnexo = false;
        for ($k = 0; $k < 3; $k++) {
            $resultado = BitrixDealHelper::editarDeal($spa, $dealId, $fields);
            if (isset($resultado['status']) && $resultado['status'] === 'sucesso') {
                $sucessoAnexo = true;
                break;
            }
            if ($k < 2) sleep(10);
        }

        if ($sucessoAnexo) {
            sleep(30); // Aguarda processamento do arquivo no Bitrix
            self::atualizarRetornoBitrix($dadosAssinatura, $spa, $dealId, true, $documentKey, 'Documento assinado e arquivo anexado com sucesso.');
            self::limparCamposBitrix($spa, $dealId, $dadosAssinatura);
            self::moverEtapaBitrix($spa, $dealId, $statusClosed['etapa_concluida'] ?? null);
            LogHelper::logClickSign("Processo de assinatura finalizado com sucesso | Documento: $documentKey", 'service');
            return ['success' => true, 'mensagem' => 'Arquivo baixado, anexado e mensagem atualizada no Bitrix.'];
        } else {
            LogHelper::logClickSign("ERRO: Falha ao anexar arquivo após 3 tentativas.", 'documentoDisponivel');
            return ['success' => false, 'mensagem' => "Erro ao anexar arquivo no Bitrix."];
        }
    }

    // Limpa os campos de controle da ClickSign no deal após a finalização.
    private static function limparCamposBitrix(string $spa, string $dealId, array $dadosAssinatura)
    {
        $fieldsLimpeza = [];
        $camposParaLimpar = [
            'campo_contratante', 'campo_contratada', 'campo_testemunhas',
            'campo_arquivoaserassinado', 'campo_data', 'campo_idclicksign'
        ];
        foreach ($camposParaLimpar as $campo) {
            if (!empty($dadosAssinatura[$campo])) {
                $fieldsLimpeza[$dadosAssinatura[$campo]] = '';
            }
        }
        
        // Limpeza dos campos de signatários que vêm do config_extra
        $configExtra = $GLOBALS['ACESSO_AUTENTICADO']['config_extra'] ?? null;
        $configJson = $configExtra ? json_decode($configExtra, true) : [];
        $spaKey = 'SPA_' . $spa;
        $camposConfig = $configJson[$spaKey]['campos'] ?? [];
        if (!empty($camposConfig['signatarios_assinar'])) $fieldsLimpeza[$camposConfig['signatarios_assinar']] = '';
        if (!empty($camposConfig['signatarios_assinaram'])) $fieldsLimpeza[$camposConfig['signatarios_assinaram']] = '';

        if (!empty($fieldsLimpeza)) {
            BitrixDealHelper::editarDeal($spa, $dealId, $fieldsLimpeza);
        }
    }

    // Move o deal para a etapa de concluído no Bitrix.
    private static function moverEtapaBitrix(string $spa, string $dealId, ?string $etapaConcluidaNome)
    {
        if (!$etapaConcluidaNome) return;

        $etapas = BitrixHelper::consultarEtapasPorTipo($spa);
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
            LogHelper::logClickSign("AVISO: Etapa '$etapaConcluidaNome' não encontrada para a SPA $spa.", 'service');
        }
    }

    // Cancela um documento na ClickSign e atualiza o Bitrix.
    public static function cancelarDocumento(array $params): array
    {
        $authData = self::getAuthAndDocumentKey($params);
        if (!$authData['success']) {
            return $authData;
        }
        
        $documentKey = $authData['documentKey'];
        $resultado = ClickSignHelper::cancelarDocumento($documentKey);

        if (isset($resultado['document'])) {
            $mensagem = "Documento ($documentKey) cancelado com sucesso.";
            self::atualizarRetornoBitrix($params, $params['spa'], $params['deal'], true, $documentKey, $mensagem);
            return ['success' => true, 'mensagem' => $mensagem];
        } else {
            $erro = $resultado['errors'][0] ?? 'Erro desconhecido ao cancelar.';
            $mensagem = "Falha ao cancelar documento ($documentKey): $erro";
            self::atualizarRetornoBitrix($params, $params['spa'], $params['deal'], false, $documentKey, $mensagem);
            return ['success' => false, 'mensagem' => $mensagem, 'details' => $resultado];
        }
    }

    // Atualiza a data de um documento na ClickSign e atualiza o Bitrix.
    public static function atualizarDataDocumento(array $params): array
    {
        $authData = self::getAuthAndDocumentKey($params);
        if (!$authData['success']) {
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
        
        $novaDataFormatada = substr($novaData, 0, 10); // Formato YYYY-MM-DD
        $payload = ['document' => ['deadline_at' => $novaDataFormatada]];
        $resultado = ClickSignHelper::atualizarDocumento($documentKey, $payload);

        if (isset($resultado['document'])) {
            $mensagem = "Data do documento atualizada para $novaDataFormatada.";
            self::atualizarRetornoBitrix($params, $entityId, $id, true, $documentKey, $mensagem);
            return ['success' => true, 'mensagem' => $mensagem];
        } else {
            $erro = $resultado['errors'][0] ?? 'Erro desconhecido ao atualizar data.';
            $mensagem = "Falha ao atualizar data do documento: $erro";
            self::atualizarRetornoBitrix($params, $entityId, $id, false, $documentKey, $mensagem);
            return ['success' => false, 'mensagem' => $mensagem, 'details' => $resultado];
        }
    }

    // Método auxiliar para autenticar e obter a chave do documento do Bitrix.
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
            self::atualizarRetornoBitrix($params, $entityId, $id, false, null, 'Acesso não autorizado ou incompleto');
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
            // Retorna sucesso para ignorar silenciosamente, evitando erros em gatilhos concorrentes.
            return ['success' => true, 'documentKey' => null, 'mensagem' => 'Ação ignorada, nenhum documento para atualizar.'];
        }

        return [
            'success' => true,
            'documentKey' => $documentKey,
            'fieldsConfig' => $fieldsConfig
        ];
    }
}
