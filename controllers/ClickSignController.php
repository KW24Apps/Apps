<?php
namespace Controllers;

require_once __DIR__ . '/../helpers/BitrixDealHelper.php';
require_once __DIR__ . '/../helpers/BitrixContactHelper.php';
require_once __DIR__ . '/../helpers/ClickSignHelper.php';
require_once __DIR__ . '/../helpers/LogHelper.php';
require_once __DIR__ . '/../dao/AplicacaoAcessoDAO.php';
require_once __DIR__ . '/../helpers/UtilHelpers.php';

use Helpers\BitrixDealHelper;
use Helpers\BitrixContactHelper;
use Helpers\ClickSignHelper;
use Helpers\LogHelper;
use dao\AplicacaoAcessoDAO;
use Helpers\BitrixHelper;
use Helpers\UtilHelpers;
use PDOException;

class ClickSignController
{
    // Método para gerar assinatura na ClickSign
    public static function GerarAssinatura()
    {
        // Define headers para resposta JSON
        header('Content-Type: application/json; charset=utf-8');
        
        $params = $_GET;
        $entityId = $params['spa'] ?? $params['entityId'] ?? null;
        $id = $params['deal'] ?? $params['id'] ?? null;

        $fields = [];
        foreach ($params as $campo => $valor) {
            if (!in_array($campo, ['spa', 'entityId', 'deal', 'id']) && $valor) {
                $fields[$campo] = $valor;
            }
        }

        LogHelper::logClickSign("Início do processo de assinatura", 'controller');

        if (empty($id) || empty($entityId)) {
            LogHelper::logClickSign("Parâmetros obrigatórios ausentes", 'controller');
            $response = ['success' => false, 'mensagem' => 'Parâmetros obrigatórios ausentes.'];
            echo json_encode($response, JSON_UNESCAPED_UNICODE);
            return $response;
        }

        $tokenClicksign = $GLOBALS['ACESSO_AUTENTICADO']['clicksign_token'] ?? null;

        if (!$tokenClicksign) {
            LogHelper::logClickSign("Token ClickSign ausente", 'controller');
            $response = ['success' => false, 'mensagem' => 'Acesso não autorizado ou incompleto.'];
            echo json_encode($response, JSON_UNESCAPED_UNICODE);
            return $response;
        }

        $registro = BitrixDealHelper::consultarDeal($entityId, $id, $fields);
        $dados = $registro['result'] ?? [];



        // Lista de todos os campos que dependem do mapeamento
        $camposNecessarios = [
            'contratante',
            'contratada',
            'testemunhas',
            'data',
            'arquivoaserassinado',
            'arquivoassinado',
            'idclicksign',
            'retorno'
        ];

        // Extrai chaves camelCase corretas para todos os campos necessários
        $mapCampos = [];
        foreach ($camposNecessarios as $campo) {
            if (!empty($params[$campo])) {
                $normalizado = BitrixHelper::formatarCampos([$params[$campo] => null]);
                $mapCampos[$campo] = array_key_first($normalizado);
            }
        }

        // Extração dos dados (acessando sempre o índice 'valor')
        $idsContratante = isset($mapCampos['contratante']) ? ($dados[$mapCampos['contratante']]['valor'] ?? null) : null;
        $idsContratada = isset($mapCampos['contratada']) ? ($dados[$mapCampos['contratada']]['valor'] ?? null) : null;
        $idsTestemunhas = isset($mapCampos['testemunhas']) ? ($dados[$mapCampos['testemunhas']]['valor'] ?? null) : null;
        $dataAssinatura = isset($mapCampos['data']) ? ($dados[$mapCampos['data']]['valor'] ?? null) : null;

        if ($dataAssinatura) {
            // Remover tudo após a data, inclusive a hora e o fuso horário
            $dataAssinatura = substr($dataAssinatura, 0, 10); // "2025-07-24"
        }

        // Se a data estiver no formato DD/MM/YYYY, converta para YYYY-MM-DD
        if ($dataAssinatura && strpos($dataAssinatura, '/') !== false) {
            $partes = explode('/', $dataAssinatura);
            $dataAssinatura = "{$partes[2]}-{$partes[1]}-{$partes[0]}";
        }

        // Consulta e validação dos signatários
        // Sugestão: mover para UtilHelpers se for reutilizar em outros lugares
        function forcarArray($val) {
            if (is_array($val)) return $val;
            if ($val === null || $val === '') return [];
            return [$val];
        }
        
        $idsParaConsultar = [
            'contratante' => forcarArray($idsContratante),
            'contratada'  => forcarArray($idsContratada),
            'testemunha'  => forcarArray($idsTestemunhas),
        ];

        $contatosConsultados = BitrixContactHelper::consultarContatos(
            $idsParaConsultar,
            ['NAME', 'LAST_NAME', 'EMAIL']
        );

        $signatarios = [
            'contratante' => [],
            'contratada'  => [],
            'testemunha'  => [],
        ];

        $erroSignatario = false;
        $erroMensagem = '';
        $qtdSignatarios = 0;

        foreach ($contatosConsultados as $papel => $listaContatos) {
            foreach ($listaContatos as $contato) {
                $nome = $contato['NAME'] ?? '';
                $sobrenome = $contato['LAST_NAME'] ?? '';
                $email = '';
                if (!empty($contato['EMAIL'])) {
                    $emailData = $contato['EMAIL'];
                    if (is_array($emailData)) {
                        $email = $emailData[0]['VALUE'] ?? '';
                    } else {
                        $email = $emailData;
                    }
                }

                if (!$nome || !$sobrenome || !$email) {
                    $erroSignatario = true;
                    $erroMensagem = "Dados faltantes nos signatários ($papel)";
                    break 2;
                }

                $signatarios[$papel][] = [
                    'nome' => $nome,
                    'sobrenome' => $sobrenome,
                    'email' => $email,
                ];
                $qtdSignatarios++;
            } 
        }

        if ($erroSignatario) {
            LogHelper::logClickSign("Erro: $erroMensagem", 'controller');
            self::atualizarRetornoBitrix($params, $entityId, $id,false, null);
            $response = ['success' => false, 'mensagem' => $erroMensagem];
            echo json_encode($response, JSON_UNESCAPED_UNICODE);
            return $response;
        }

        LogHelper::logClickSign("Signatários validados | Total: $qtdSignatarios", 'controller');


        // Valida arquivo
        $campoArquivo = null;
        $urlMachine = null;
        if (isset($mapCampos['arquivoaserassinado']) && isset($dados[$mapCampos['arquivoaserassinado']])) {
            $campoArquivo = $dados[$mapCampos['arquivoaserassinado']];
        }



        if (is_array($campoArquivo)) {
            // Se vier no formato esperado do Bitrix (com 'valor' => [ { ... } ])
            if (isset($campoArquivo['valor'][0]['urlMachine'])) {
                $urlMachine = $campoArquivo['valor'][0]['urlMachine'];
            } elseif (isset($campoArquivo['valor'][0]['url'])) {
                // fallback para url comum
                $urlMachine = $campoArquivo['valor'][0]['url'];
            } elseif (isset($campoArquivo['urlMachine'])) {
                $urlMachine = $campoArquivo['urlMachine'];
            }
        }


        $arquivoInfo = ['urlMachine' => $urlMachine];
        $arquivoConvertido = UtilHelpers::baixarArquivoBase64($arquivoInfo);

        if (!$arquivoConvertido) {
            LogHelper::logClickSign("Erro ao converter o arquivo", 'controller');
            self::atualizarRetornoBitrix($params, $entityId, $id,false, null);
            $response = ['success' => false, 'mensagem' => 'Erro ao converter o arquivo.'];
            echo json_encode($response, JSON_UNESCAPED_UNICODE);
            return $response;
        }

        // Monta o payload para ClickSign
        $payloadClickSign = [
            'document' => [
                'content_base64' => $arquivoConvertido['base64'],
                'name'           => $arquivoConvertido['nome'],
                'path'           => '/' . $arquivoConvertido['nome'],
                'content_type'   => $arquivoConvertido['mime'],
                'remind_interval'    => 2,
                'deadline_at'    => $dataAssinatura 
            ]
        ];

        // Criação do documento só após todas as validações
        $retornoClickSign = ClickSignHelper::criarDocumento($payloadClickSign, $tokenClicksign);

        if (isset($retornoClickSign['document']['key'])) {
            $documentKey = $retornoClickSign['document']['key'];
            LogHelper::logClickSign("Documento criado na ClickSign | ID: $documentKey", 'controller');

            // Mapeamento para sign_as
            $mapSignAs = [
                'contratante' => 'contractor',
                'contratada'  => 'contractee',
                'testemunha'  => 'witness',
            ];

            $sucessoVinculo = true;
            $signatariosCriados = [];
            $qtdVinculos = 0;

            foreach ($signatarios as $papel => $listaSignatarios) {
                foreach ($listaSignatarios as $signatario) {
                    $nomeCompleto = trim($signatario['nome'] . ' ' . $signatario['sobrenome']);
                    $email = $signatario['email'] ?? '';
                    $signAs = $mapSignAs[$papel] ?? 'sign';

                    $retornoSignatario = ClickSignHelper::criarSignatario($tokenClicksign, [
                        'name'  => $nomeCompleto,
                        'email' => $email,
                        'auths' => ['email']
                    ]);

                    if (empty($retornoSignatario['signer']['key'])) {
                        LogHelper::logClickSign("Falha ao criar signatário ($papel)", 'controller');
                        $sucessoVinculo = false;
                        continue;
                    }

                    $signerKey = $retornoSignatario['signer']['key'];

                    $vinculo = ClickSignHelper::vincularSignatario($tokenClicksign, [
                        'document_key' => $documentKey,
                        'signer_key'   => $signerKey,
                        'sign_as'      => $signAs,
                        'message'      => "Prezado(a) $papel,\nPor favor assine o documento.\n\nAtenciosamente,\nKWCA"
                    ]);

                    
                    if (!empty($vinculo['list']['request_signature_key'])) {
                        $mensagem = "Prezado(a), segue documento para assinatura.";
                        ClickSignHelper::enviarNotificacao($tokenClicksign, $vinculo['list']['request_signature_key'], $mensagem);
                    }

                    if (empty($vinculo['list']['key'])) {
                        LogHelper::logClickSign("Falha ao vincular signatário ($papel)", 'controller');
                        $sucessoVinculo = false;
                        continue;
                    }

                    $qtdVinculos++;
                    $signatariosCriados[] = [
                        'papel'      => $papel,
                        'signatario' => $signatario,
                        'retorno'    => $retornoSignatario,
                        'vinculo'    => $vinculo
                    ];
                }
            }

        // Teste para verificar se todos os vínculos foram criados com sucesso   
        if ($sucessoVinculo) {
            $clienteId = $GLOBALS['ACESSO_AUTENTICADO']['cliente_id'] ?? null;
            $gravado = false;
            $tentativas = 0;
            $maxTentativas = 3;

            while ($tentativas < $maxTentativas && !$gravado) {
                try {
                    AplicacaoAcessoDAO::registrarAssinaturaClicksign([
                        'document_key'               => $documentKey,
                        'cliente_id'                 => $clienteId,
                        'deal_id'                    => $id,
                        'spa'                        => $entityId,
                        'campo_contratante'          => $params['contratante'] ?? null,
                        'campo_contratada'           => $params['contratada'] ?? null,
                        'campo_testemunhas'          => $params['testemunhas'] ?? null,
                        'campo_data'                 => $params['data'] ?? null,
                        'campo_arquivoaserassinado'  => $params['arquivoaserassinado'] ?? null,
                        'campo_arquivoassinado'      => $params['arquivoassinado'] ?? null,
                        'campo_idclicksign'          => $params['idclicksign'] ?? null,
                        'campo_retorno'              => $params['retorno'] ?? null
                    ]);
                    $gravado = true;
                } catch (PDOException $e) {
                    $tentativas++;
                    if ($tentativas < $maxTentativas) sleep(10);
                }
            }

            if ($gravado) {
                self::atualizarRetornoBitrix($params, $entityId, $id, true, $documentKey, 'Documento enviado para assinatura');
                LogHelper::logClickSign("Documento enviado para assinatura e dados atualizados no Bitrix com sucesso", 'controller');
                $response = [
                    'success' => true,
                    'mensagem' => 'Documento enviado para assinatura',
                    'document_key' => $documentKey,
                    'qtd_signatarios' => $qtdSignatarios,
                    'qtd_vinculos' => $qtdVinculos
                ];
                echo json_encode($response, JSON_UNESCAPED_UNICODE);
                return $response;
            } else {
                self::atualizarRetornoBitrix($params, $entityId, $id, true, $documentKey, 'Assinatura criada, mas falha ao gravar no banco');
                LogHelper::logClickSign("Documento finalizado, mas erro ao gravar controle de assinatura", 'controller');
                $response = [
                    'success' => false,
                    'mensagem' => 'Assinatura criada, mas falha ao gravar controle de assinatura.',
                    'document_key' => $documentKey
                ];
                echo json_encode($response, JSON_UNESCAPED_UNICODE);
                return $response;
            }
        } else {
            $response = [
                'success' => false,
                'mensagem' => 'Documento criado, mas houve falha em um ou mais vínculos de signatários.',
                'document_key' => $documentKey,
                'qtd_signatarios' => $qtdSignatarios,
                'qtd_vinculos' => $qtdVinculos
            ];
            echo json_encode($response, JSON_UNESCAPED_UNICODE);
            return $response;
            }
        }
    }
 
    // Função auxiliar para atualizar status e ID no Bitrix
    private static function atualizarRetornoBitrix($params, $spa, $dealId, $sucesso, $documentKey, $mensagemCustomizada = null)
    {
        $campoRetorno = $params['retorno'] ?? null;
        $campoIdClickSign = $params['idclicksign'] ?? null;

        $mensagemRetorno = $mensagemCustomizada ?? "Documento enviado para assinatura";
        $mensagemErro = "Erro no envio do documento para assinatura";

        $fields = [];

        if ($campoRetorno) {
            $fields[$campoRetorno] = $sucesso ? $mensagemRetorno : $mensagemErro;
        }

        if ($sucesso && $campoIdClickSign && $documentKey) {
            $fields[$campoIdClickSign] = $documentKey;
        }

        if (empty($fields)) {
            LogHelper::logClickSign("ERRO: Nenhum campo para atualizar - fields está vazio", 'atualizarRetornoBitrix');
            return ['success' => false, 'error' => 'Nenhum campo para atualizar'];
        }

        $response = BitrixDealHelper::editarDeal($spa, $dealId, $fields);

        // Log apenas em caso de erro
        if (!isset($response['success']) || !$response['success']) {
            LogHelper::logClickSign("ERRO atualizarRetornoBitrix | spa: $spa | dealId: $dealId | params: " . json_encode($params) . " | fields: " . json_encode($fields) . " | response: " . json_encode($response), 'atualizarRetornoBitrix');
        }

        return $response;

    }

    // Novo nome e organização: retornoClickSign
    public static function retornoClickSign($requestData)
    {
        // 1. Log do JSON recebido
        $rawBody = file_get_contents('php://input');
        $documentKey = $requestData['document']['key'] ?? null;
        
        // 2. Valida campos obrigatórios
        if (empty($documentKey)) {
            LogHelper::logClickSign("Parâmetros obrigatórios ausentes | DocumentKey: $documentKey", 'controller');
            return ['success' => false, 'mensagem' => 'Parâmetros obrigatórios ausentes.'];
        }

        // 3. Validação HMAC
        $secret = $GLOBALS['ACESSO_AUTENTICADO']['clicksign_secret'] ?? null;
        $headerSignature = $_SERVER['HTTP_CONTENT_HMAC'] ?? null;

        if (!ClickSignHelper::validarHmac($rawBody, $secret, $headerSignature)) {
            LogHelper::logClickSign("Assinatura HMAC inválida", 'controller');
            return ['success' => false, 'mensagem' => 'Assinatura HMAC inválida.'];
        }

        // 4. Consulta todos os dados e status do documento (tudo em uma vez só)
        $dadosAssinatura = AplicacaoAcessoDAO::obterAssinaturaClickSign($documentKey);
        if (!$dadosAssinatura) {
            LogHelper::logClickSign("Documento não encontrado | DocumentKey: $documentKey", 'controller');
            return ['success' => false, 'mensagem' => 'Documento não encontrado.'];
        }

        $spa = $dadosAssinatura['spa'] ?? null;
        $dealId = $dadosAssinatura['deal_id'] ?? null;
        $campoArquivoAssinado = $dadosAssinatura['campo_arquivoassinado'] ?? null;
        $campoRetorno = $dadosAssinatura['campo_retorno'] ?? null;

        if (!$campoRetorno) {
            LogHelper::logClickSign("Campo retorno não encontrado | DocumentKey: $documentKey", 'controller');
            return ['success' => false, 'mensagem' => 'Campo retorno não encontrado na assinatura.'];
        }

        // 5. Valida e executa conforme o evento, usando apenas $dadosAssinatura já carregado
        $evento = $requestData['event']['name'] ?? null;
        switch ($evento) {
            case 'sign':
                $assinante = $requestData['event']['data']['signer']['email'] ?? null;
                if (!empty($dadosAssinatura['assinatura_processada']) && strpos($dadosAssinatura['assinatura_processada'], $assinante) !== false) {
                    LogHelper::logClickSign("Assinatura duplicada ignorada para $assinante | Documento: $documentKey", 'controller');
                    return ['success' => true, 'mensagem' => 'Assinatura já processada.'];
                }
                AplicacaoAcessoDAO::salvarStatus($documentKey, null, ($dadosAssinatura['assinatura_processada'] ?? '') . ";" . $assinante);
                return self::assinaturaRealizada($requestData, $spa, $dealId, $campoRetorno);

            case 'deadline':
            case 'cancel':
            case 'auto_close':
                if (!empty($dadosAssinatura['documento_fechado_processado'])) {
                    LogHelper::logClickSign("Evento duplicado ignorado ($evento) | Documento: $documentKey", 'controller');
                    return ['success' => true, 'mensagem' => 'Evento de documento fechado já processado.'];
                }
                AplicacaoAcessoDAO::salvarStatus($documentKey, $evento, null, true);
                LogHelper::logClickSign("Processado evento $evento e marcado como fechado | Documento: $documentKey", 'controller');
                return self::documentoFechado($requestData, $spa, $dealId, $documentKey, $campoRetorno);

            case 'document_closed':
                if (!empty($dadosAssinatura['documento_disponivel_processado'])) {
                    LogHelper::logClickSign("Documento já disponível, evento duplicado ignorado | Documento: $documentKey", 'controller');
                    return ['success' => true, 'mensagem' => 'Documento já disponível e processado anteriormente.'];
                }
                AplicacaoAcessoDAO::salvarStatus($documentKey, null, null, null, true);
                LogHelper::logClickSign("Processado evento document_closed e documento marcado como disponível | Documento: $documentKey", 'controller');
                return self::documentoDisponivel($requestData, $spa, $dealId, $campoArquivoAssinado, $campoRetorno, $documentKey);

            default:
                LogHelper::logClickSign("Evento não tratado: $evento", 'controller');
                return ['success' => true, 'mensagem' => 'Evento recebido sem ação específica.'];
        }
    }

    // Método para tratar eventos assinatura de Signatario
    private static function assinaturaRealizada($requestData, $spa, $dealId, $campoRetorno)
    {
        // 1. Validação de parâmetros obrigatórios
        if (empty($spa) || empty($dealId) || empty($campoRetorno)) {
            LogHelper::logClickSign("ERRO: Parâmetros obrigatórios ausentes para assinatura | spa: $spa | dealId: $dealId | campoRetorno: $campoRetorno", 'assinaturaRealizada');
            return ['success' => false, 'mensagem' => 'Parâmetros obrigatórios ausentes para processar assinatura.'];
        }

        // 2. Extrai signatário do evento
        $signer = $requestData['event']['data']['signer'] ?? null;

        // 3. Se encontrou signatário, monta mensagem
        if ($signer) {
            $nome  = $signer['name']  ?? '';
            $email = $signer['email'] ?? '';

            if (empty($nome) || empty($email)) {
                LogHelper::logClickSign("ERRO: Dados do signatário incompletos | nome: $nome | email: $email", 'assinaturaRealizada');
                return ['success' => false, 'mensagem' => 'Dados do signatário incompletos.'];
            }

            $mensagem = "Assinatura feita por $nome - $email";

            // 4. Atualiza status no Bitrix (NÃO retorna arquivo)
            $resultado = self::atualizarRetornoBitrix(
                ['retorno' => $campoRetorno],
                $spa,
                $dealId,
                true,
                null, // Não envia documentKey aqui
                $mensagem
            );

            if (isset($resultado['success']) && $resultado['success']) {
                return ['success' => true, 'mensagem' => 'Assinatura processada e retorno atualizado.'];
            } else {
                $erroDetalhado = isset($resultado['error']) ? $resultado['error'] : json_encode($resultado);
                LogHelper::logClickSign("ERRO: Falha ao atualizar mensagem de assinatura no Bitrix | erro: $erroDetalhado", 'assinaturaRealizada');
                return ['success' => false, 'mensagem' => 'Erro ao atualizar mensagem de assinatura no Bitrix.'];
            }
        }

        // 5. Caso falte dados
        LogHelper::logClickSign("ERRO: Dados do signatário não encontrados no evento", 'assinaturaRealizada');
        return ['success' => false, 'mensagem' => 'Dados do signatário não encontrados.'];
    }

    // Método para tratar eventos de fechamento de documento
    private static function documentoFechado($requestData, $spa, $dealId, $documentKey, $campoRetorno)
    {
        // 1. Salva status de fechamento no banco
        try {
            AplicacaoAcessoDAO::salvarStatus($documentKey, $requestData['event']['name']);
        } catch (\Exception $e) {
            LogHelper::logClickSign("Erro ao salvar status_close: " . $e->getMessage(), 'controller');
            return ['success' => false, 'mensagem' => 'Erro ao salvar status_close'];
        }

        // 2. Se deadline ou cancel: já atualiza status no Bitrix (SEM arquivo)
        $evento = $requestData['event']['name'];
        if (in_array($evento, ['deadline', 'cancel'])) {
            // Corrigido: operador ternário agora define a mensagem corretamente
            $mensagem = $evento === 'deadline'
                ? 'Assinatura cancelada por prazo finalizado.'
                : 'Assinatura cancelada manualmente.';
            // Corrigido: chamada agora passa todos os parâmetros obrigatórios
            self::atualizarRetornoBitrix(
                ['retorno' => $campoRetorno],
                $spa,
                $dealId,
                true,
                null,
                $mensagem
            );
            return ['success' => true, 'mensagem' => "Evento $evento processado com atualização imediata no Bitrix."];
        }

        // 3. Se auto_close: só salva, aguarda document_closed para baixar o arquivo
        if ($evento === 'auto_close') {
            return ['success' => true, 'mensagem' => 'Evento auto_close salvo, aguardando document_closed.'];
        }

        // 4. Outros eventos (não esperado)
        LogHelper::logClickSign("Evento de fechamento não tratado: $evento", 'controller');
        return ['success' => true, 'mensagem' => "Evento de fechamento $evento ignorado."];
    }

    // Método para tratar eventos Documento disponível para download
    private static function documentoDisponivel($requestData, $spa, $dealId, $campoArquivoAssinado, $campoRetorno, $documentKey)
    {
        // 1. Validação de parâmetros obrigatórios ANTES de qualquer processamento
        if (empty($spa) || empty($dealId) || empty($documentKey)) {
            LogHelper::logClickSign("ERRO: Parâmetros básicos obrigatórios ausentes | spa: $spa | dealId: $dealId | documentKey: $documentKey", 'documentoDisponivel');
            return ['success' => false, 'mensagem' => 'Parâmetros básicos obrigatórios ausentes.'];
        }

        if (empty($campoRetorno)) {
            LogHelper::logClickSign("ERRO: Campo retorno obrigatório ausente | campoRetorno: $campoRetorno", 'documentoDisponivel');
            return ['success' => false, 'mensagem' => 'Campo retorno obrigatório ausente.'];
        }

        // 2. Buscar status fechado no banco
        $tentativasStatus = 15;
        $esperaStatus = 10;
        $statusClosed = null;

        for ($i = 0; $i < $tentativasStatus; $i++) {
            $statusClosed = AplicacaoAcessoDAO::obterAssinaturaClickSign($documentKey);
            if ($statusClosed !== null) break;
            sleep($esperaStatus);
        }

        if ($statusClosed === null) {
            LogHelper::logClickSign("ERRO: StatusClosed não encontrado após $tentativasStatus tentativas | documentKey: $documentKey", 'documentoDisponivel');
            return ['success' => false, 'mensagem' => 'StatusClosed não encontrado.'];
        }

        // 3. Se for deadline ou cancel, ignora (documento cancelado)
        if (in_array($statusClosed['status_closed'] ?? '', ['deadline', 'cancel'])) {
            LogHelper::logClickSign("Documento cancelado, evento ignorado | status: {$statusClosed['status_closed']}", 'documentoDisponivel');
            return ['success' => true, 'mensagem' => "Evento {$statusClosed['status_closed']} ignorado."];
        }

        // 4. Se for auto_close, baixar e anexar o arquivo assinado
        if (($statusClosed['status_closed'] ?? '') === 'auto_close') {
            
            // 4.1. Validação do campo arquivo assinado apenas quando necessário
            if (empty($campoArquivoAssinado)) {
                LogHelper::logClickSign("AVISO: Campo arquivo assinado não configurado, enviando apenas mensagem final | campoArquivoAssinado: $campoArquivoAssinado", 'documentoDisponivel');
                
                // Envia apenas mensagem final sem anexar arquivo
                $resultadoMensagem = self::atualizarRetornoBitrix([
                    'retorno' => $campoRetorno
                ], $spa, $dealId, true, null, 'Documento assinado com sucesso.');

                if (isset($resultadoMensagem['success']) && $resultadoMensagem['success']) {
                    return ['success' => true, 'mensagem' => 'Mensagem final enviada (sem anexo de arquivo).'];
                } else {
                    $erroDetalhado = isset($resultadoMensagem['error']) ? $resultadoMensagem['error'] : json_encode($resultadoMensagem);
                    LogHelper::logClickSign("ERRO: Falha ao enviar mensagem final | erro: $erroDetalhado", 'documentoDisponivel');
                    return ['success' => false, 'mensagem' => 'Falha ao enviar mensagem final.'];
                }
            }

            // 4.2. Processar download e anexo do arquivo
            $tentativasDownload = 15;
            $esperaDownload = 30;

            $token = $GLOBALS['ACESSO_AUTENTICADO']['clicksign_token'] ?? null;
            if (empty($token)) {
                LogHelper::logClickSign("ERRO: Token ClickSign ausente ao tentar baixar o arquivo assinado", 'documentoDisponivel');
                return ['success' => false, 'mensagem' => 'Token ClickSign ausente. Não é possível baixar o arquivo assinado.'];
            }

            for ($j = 0; $j < $tentativasDownload; $j++) {
                $retDoc = ClickSignHelper::buscarDocumento($token, $documentKey);
                $url = $retDoc['document']['downloads']['signed_file_url'] ?? null;
                $nomeArquivo = $retDoc['document']['filename'] ?? "documento_assinado.pdf";

                if ($url) {
                    // 4.2.1. Baixa e converte o arquivo para base64
                    $arquivoInfo = [
                        'urlMachine' => $url,
                        'name' => $nomeArquivo
                    ];
                    $arquivoBase64 = UtilHelpers::baixarArquivoBase64($arquivoInfo);

                    if (!$arquivoBase64) {
                        LogHelper::logClickSign("ERRO: Erro ao baixar/converter arquivo para anexo no negócio", 'documentoDisponivel');
                        return ['success' => false, 'mensagem' => 'Falha ao converter o arquivo.'];
                    }

                    // 4.2.2. Prepara estrutura do campo para o Bitrix
                    $arquivoParaBitrix = [[
                        'filename' => $arquivoBase64['nome'],
                        'data'     => str_replace('data:' . $arquivoBase64['mime'] . ';base64,', '', $arquivoBase64['base64'])
                    ]];

                    // 4.2.3. Tenta anexar arquivo com retry
                    $fields = [
                        $campoArquivoAssinado => $arquivoParaBitrix
                    ];

                    $tentativasAnexo = 3;
                    $sucessoAnexo = false;
                    $ultimoErro = '';

                    for ($k = 0; $k < $tentativasAnexo; $k++) {
                        $resultado = BitrixDealHelper::editarDeal($spa, $dealId, $fields);

                        if (isset($resultado['success']) && $resultado['success']) {
                            $sucessoAnexo = true;
                            break;
                        } else {
                            $ultimoErro = $resultado['error'] ?? json_encode($resultado);
                            if ($k < $tentativasAnexo - 1) sleep(10); // Aguarda antes da próxima tentativa
                        }
                    }

                    if ($sucessoAnexo) {
                        // 4.2.4. Aguarda processamento do arquivo no Bitrix (30s)
                        sleep(30);

                        // 4.2.5. Atualiza campo de retorno com mensagem de sucesso
                        $resultadoMensagem = self::atualizarRetornoBitrix([
                            'retorno' => $campoRetorno
                        ], $spa, $dealId, true, null, 'Documento assinado e arquivo enviado para o Bitrix.');

                        if (isset($resultadoMensagem['success']) && $resultadoMensagem['success']) {
                            return ['success' => true, 'mensagem' => 'Arquivo baixado, anexado e mensagem atualizada no Bitrix.'];
                        } else {
                            $erroDetalhado = isset($resultadoMensagem['error']) ? $resultadoMensagem['error'] : json_encode($resultadoMensagem);
                            LogHelper::logClickSign("ERRO: Falha ao atualizar mensagem final no Bitrix | erro: $erroDetalhado", 'documentoDisponivel');
                            return ['success' => false, 'mensagem' => 'Arquivo anexado, mas falha ao atualizar mensagem no Bitrix.'];
                        }
                    } else {
                        LogHelper::logClickSign("ERRO: Falha ao anexar arquivo após $tentativasAnexo tentativas | último erro: $ultimoErro", 'documentoDisponivel');
                        return ['success' => false, 'mensagem' => "Erro ao anexar arquivo no Bitrix após $tentativasAnexo tentativas: $ultimoErro"];
                    }
                }
                sleep($esperaDownload);
            }

            LogHelper::logClickSign("ERRO: Não foi possível baixar/anexar o arquivo assinado após $tentativasDownload tentativas", 'documentoDisponivel');
            return ['success' => false, 'mensagem' => 'Não foi possível baixar/anexar o arquivo assinado.'];
        }

        // 5. Status inesperado
        LogHelper::logClickSign("AVISO: Status inesperado encontrado | status: " . ($statusClosed['status_closed'] ?? 'null'), 'documentoDisponivel');
        return ['success' => true, 'mensagem' => 'StatusClosed não tratado.'];
    }

}