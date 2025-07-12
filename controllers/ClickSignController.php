<?php
require_once __DIR__ . '/../helpers/BitrixDealHelper.php';
require_once __DIR__ . '/../helpers/BitrixContactHelper.php';
require_once __DIR__ . '/../helpers/ClickSignHelper.php';
require_once __DIR__ . '/../helpers/LogHelper.php';
require_once __DIR__ . '/../dao/AplicacaoAcessoDAO.php';

use dao\AplicacaoAcessoDAO;

class ClickSignController
{
    // Método para gerar assinatura na ClickSign
    public static function GerarAssinatura()
    {
        $params = $_GET;
        $cliente = $params['cliente'] ?? null;
        $entityId = $params['spa'] ?? $params['entityId'] ?? null;
        $id = $params['deal'] ?? $params['id'] ?? null;

        LogHelper::logClickSign("Início GerarAssinatura | Cliente: $cliente", 'controller');

        if (empty($cliente) || empty($id) || empty($entityId)) {
            LogHelper::logClickSign("Parâmetros obrigatórios ausentes", 'controller');
            return ['success' => false, 'mensagem' => 'Parâmetros obrigatórios ausentes.'];
        }

        $acesso = AplicacaoAcessoDAO::obterWebhookPermitido($cliente, 'clicksign');
        if (!$acesso || empty($acesso['webhook_bitrix']) || empty($acesso['clicksign_token'])) {
            LogHelper::logClickSign("Acesso ClickSign não autorizado ou incompleto | Cliente: $cliente", 'controller');
            return ['success' => false, 'mensagem' => 'Acesso não autorizado ou incompleto.'];
        }

        $webhook = $acesso['webhook_bitrix'];
        $tokenClicksign = $acesso['clicksign_token'];

        // Campos para consulta do deal
        $camposConsulta = [
            'contratante',
            'contratada',
            'testemunhas',
            'data',
            'arquivoaserassinado'
        ];

        $fields = [];
        foreach ($camposConsulta as $campo) {
            if (!empty($params[$campo])) $fields[] = $params[$campo];
        }

        $registro = BitrixDealHelper::consultarDeal($entityId, $id, $fields, $webhook);
        $dados = $registro['result']['item'] ?? [];

        // Extrai chaves camelCase corretas
        $mapCampos = [];
        foreach ($camposConsulta as $campo) {
            if (!empty($params[$campo])) {
                $normalizado = BitrixHelper::formatarCampos([$params[$campo] => null]);
                $mapCampos[$campo] = array_key_first($normalizado);
            }
        }

        // Extração dos dados
        $idsContratante = $dados[$mapCampos['contratante'] ?? ''] ?? null;
        $idsContratada = $dados[$mapCampos['contratada'] ?? ''] ?? null;
        $idsTestemunhas = $dados[$mapCampos['testemunhas'] ?? ''] ?? null;
        $dataAssinatura = $dados[$mapCampos['data'] ?? ''] ?? null;

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
            $webhook,
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
            LogHelper::logClickSign("Erro: $erroMensagem | Cliente: $cliente", 'controller');
            self::atualizarRetornoBitrix($params, $entityId, $id, $webhook, false, null);
            return ['success' => false, 'mensagem' => $erroMensagem];
        }

        LogHelper::logClickSign("Signatários validados | Cliente: $cliente | Total: $qtdSignatarios", 'controller');

        // Valida arquivo
        $campoArquivo = $dados[$mapCampos['arquivoaserassinado'] ?? ''] ?? null;
        $urlMachine = null;

        if (is_array($campoArquivo)) {
            if (isset($campoArquivo[0]['urlMachine'])) $urlMachine = $campoArquivo[0]['urlMachine'];
            elseif (isset($campoArquivo['urlMachine'])) $urlMachine = $campoArquivo['urlMachine'];
        }

        $arquivoInfo = ['urlMachine' => $urlMachine];
        $arquivoConvertido = BitrixDealHelper::baixarArquivoBase64($arquivoInfo);

        if (!$arquivoConvertido) {
            LogHelper::logClickSign("Erro ao converter o arquivo | Cliente: $cliente", 'controller');
            self::atualizarRetornoBitrix($params, $entityId, $id, $webhook, false, null);
            return ['success' => false, 'mensagem' => 'Erro ao converter o arquivo.'];
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
            LogHelper::logClickSign("Documento criado na ClickSign | Cliente: $cliente | ID: $documentKey", 'controller');

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
                        LogHelper::logClickSign("Falha ao criar signatário ($papel) | Cliente: $cliente", 'controller');
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
                        LogHelper::logClickSign("Falha ao vincular signatário ($papel) | Cliente: $cliente", 'controller');
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

           
            // Atualiza Bitrix com sucesso ou erro nos vínculos
            self::atualizarRetornoBitrix($params, $entityId, $id, $webhook, $sucessoVinculo, $documentKey);

            // Resposta para Postman ou sistema externo
            if ($sucessoVinculo) {
                // --- GRAVAÇÃO NA TABELA DE ASSINATURAS ---
                $clienteId = $acesso['cliente_id'] ?? null;
                
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
                // --- FIM DA GRAVAÇÃO ---
                return [
                    'success' => true,
                    'mensagem' => 'Documento enviado para assinatura',
                    'document_key' => $documentKey,
                    'qtd_signatarios' => $qtdSignatarios,
                    'qtd_vinculos' => $qtdVinculos
                ];
            } else {
                return [
                    'success' => false,
                    'mensagem' => 'Documento criado, mas houve falha em um ou mais vínculos de signatários.',
                    'document_key' => $documentKey,
                    'qtd_signatarios' => $qtdSignatarios,
                    'qtd_vinculos' => $qtdVinculos
                ];
            }
        } else {
            LogHelper::logClickSign("Erro ao criar documento na ClickSign | Cliente: $cliente", 'controller');
            self::atualizarRetornoBitrix($params, $entityId, $id, $webhook, false, null);
            return ['success' => false, 'mensagem' => 'Erro no envio do documento para assinatura'];
        }
    }

    // Função auxiliar para atualizar status e ID no Bitrix
    private static function atualizarRetornoBitrix($params, $spa, $dealId, $webhook, $sucesso, $documentKey, $mensagemCustomizada = null)
    {
        $campoRetorno = $params['retorno'] ?? null;
        $campoIdClickSign = $params['idclicksign'] ?? null;

        $mensagemRetorno = $mensagemCustomizada ?? "Documento enviado para assinatura";
        $mensagemErro = "Erro no envio do documento para assinatura";

        if ($sucesso) {
            $dados = [
                'spa' => $spa,
                'deal' => $dealId,
                'webhook' => $webhook,
                $campoRetorno => $mensagemRetorno,
            ];
            if ($documentKey) {
                $dados[$campoIdClickSign] = $documentKey;
            }
        } else {
            $dados = [
                'spa' => $spa,
                'deal' => $dealId,
                'webhook' => $webhook,
                $campoRetorno => $mensagemErro
            ];
        }

        LogHelper::logClickSign("Dados enviados para Bitrix: " . json_encode($dados), 'controller');

        $response = BitrixDealHelper::editarNegociacao($dados);

        LogHelper::logClickSign("Resposta da API Bitrix: " . json_encode($response), 'controller');
    }

    // Novo nome e organização: retornoClickSign
    public static function retornoClickSign($requestData)
    {
        // 1. Log do JSON recebido
        $rawBody = file_get_contents('php://input');
        
        // 2. Identifica cliente (GET) e documentKey (JSON)
        $cliente = $_GET['cliente'] ?? null;
        $documentKey = $requestData['document']['key'] ?? null;
        
        // 3. Valida campos obrigatórios
        if (empty($cliente) || empty($documentKey)) {
            LogHelper::logClickSign("Parâmetros obrigatórios ausentes | Cliente: $cliente | DocumentKey: $documentKey", 'controller');
            return ['success' => false, 'mensagem' => 'Parâmetros obrigatórios ausentes.'];
        }

        // 4. Consulta permissões e secrets
        $acesso = AplicacaoAcessoDAO::obterWebhookPermitido($cliente, 'clicksign');
        if (!$acesso || empty($acesso['clicksign_secret'])) {
            LogHelper::logClickSign("Acesso não autorizado ou Secret não encontrado | Cliente: $cliente", 'controller');
            return ['success' => false, 'mensagem' => 'Acesso não autorizado ou Secret não encontrado.'];
        }
        $webhook = $acesso['webhook_bitrix'];
        // 5. Validação HMAC
        $secret = $acesso['clicksign_secret'];
        $headerSignature = $_SERVER['HTTP_CONTENT_HMAC'] ?? null;

        if (!ClickSignHelper::validarHmac($rawBody, $secret, $headerSignature)) {
            LogHelper::logClickSign("Assinatura HMAC inválida | Cliente: $cliente", 'controller');
            return ['success' => false, 'mensagem' => 'Assinatura HMAC inválida.'];
        }

        // 6. Consulta todos os dados e status do documento (tudo em uma vez só)
        $dadosAssinatura = AplicacaoAcessoDAO::obterAssinaturaClickSign($documentKey);
        if (!$dadosAssinatura) {
            LogHelper::logClickSign("Documento não encontrado | Cliente: $cliente | DocumentKey: $documentKey", 'controller');
            return ['success' => false, 'mensagem' => 'Documento não encontrado.'];
        }

        $spa = $dadosAssinatura['spa'] ?? null;
        $dealId = $dadosAssinatura['deal_id'] ?? null;
        $campoArquivoAssinado = $dadosAssinatura['campo_arquivoassinado'] ?? null;
        $campoRetorno = $dadosAssinatura['campo_retorno'] ?? null;

        if (!$campoRetorno) {
            LogHelper::logClickSign("Campo retorno não encontrado | Cliente: $cliente | DocumentKey: $documentKey", 'controller');
            return ['success' => false, 'mensagem' => 'Campo retorno não encontrado na assinatura.'];
        }

        // 7. Identifica tipo de evento (ex: sign, deadline, cancel, auto_close, document_closed)
        $evento = $requestData['event']['name'] ?? null;

        if (in_array($evento, ['auto_close', 'document_closed'])) {
            LogHelper::logDocumentoAssinado("evento=$evento | spa=$spa | dealId=$dealId | campoArquivoAssinado=$campoArquivoAssinado | campoRetorno=$campoRetorno | documentKey=$documentKey", 'retornoClickSign');
        }

        // 8. Valida e executa conforme o evento, usando apenas $dadosAssinatura já carregado
        switch ($evento) {
            case 'sign':
                $assinante = $requestData['event']['data']['signer']['email'] ?? null;
                if (!empty($dadosAssinatura['assinatura_processada']) && strpos($dadosAssinatura['assinatura_processada'], $assinante) !== false) {
                    LogHelper::logClickSign("Assinatura duplicada ignorada para $assinante | Documento: $documentKey", 'controller');
                    return ['success' => true, 'mensagem' => 'Assinatura já processada.'];
                }
                AplicacaoAcessoDAO::salvarStatus($documentKey, null, ($dadosAssinatura['assinatura_processada'] ?? '') . ";" . $assinante);
                return self::assinaturaRealizada($requestData, $acesso, $spa, $dealId, $campoRetorno);

            case 'deadline':
            case 'cancel':
            case 'auto_close':
                if (!empty($dadosAssinatura['documento_fechado_processado'])) {
                    LogHelper::logClickSign("Evento duplicado ignorado ($evento) | Documento: $documentKey", 'controller');
                    return ['success' => true, 'mensagem' => 'Evento de documento fechado já processado.'];
                }
                AplicacaoAcessoDAO::salvarStatus($documentKey, $evento, null, true);
                LogHelper::logClickSign("Processado evento $evento e marcado como fechado | Documento: $documentKey", 'controller');
                return self::documentoFechado($requestData, $acesso, $spa, $dealId, $documentKey, $campoRetorno);

            case 'document_closed':
                if (!empty($dadosAssinatura['documento_disponivel_processado'])) {
                    LogHelper::logClickSign("Documento já disponível, evento duplicado ignorado | Documento: $documentKey", 'controller');
                    return ['success' => true, 'mensagem' => 'Documento já disponível e processado anteriormente.'];
                }
                AplicacaoAcessoDAO::salvarStatus($documentKey, null, null, null, true);
                LogHelper::logClickSign("Processado evento document_closed e documento marcado como disponível | Documento: $documentKey", 'controller');
                return self::documentoDisponivel($requestData, $acesso, $spa, $dealId, $campoArquivoAssinado, $campoRetorno, $documentKey, $webhook);

            default:
                LogHelper::logClickSign("Evento não tratado: $evento", 'controller');
                return ['success' => true, 'mensagem' => 'Evento recebido sem ação específica.'];
        }
    }

    // Método para tratar eventos assinatura de Signatario
    private static function assinaturaRealizada($requestData, $acesso, $spa, $dealId, $campoRetorno)
    {
        // 1. Extrai signatário do evento
        $signer = $requestData['event']['data']['signer'] ?? null;

        // 2. Se encontrou signatário, monta mensagem
        if ($signer) {
            $nome  = $signer['name']  ?? '';
            $email = $signer['email'] ?? '';

            $mensagem = "Assinatura feita por $nome - $email";

            // 3. Atualiza status no Bitrix (NÃO retorna arquivo)
            self::atualizarRetornoBitrix(
                ['retorno' => $campoRetorno],
                $spa,
                $dealId,
                $acesso['webhook_bitrix'] ?? null,
                true,
                null, // NUNCA envia documentKey ou arquivo aqui
                $mensagem
            );

            LogHelper::logClickSign("Mensagem atualizada no Bitrix: $mensagem", 'controller');

            return ['success' => true, 'mensagem' => 'Assinatura processada e retorno atualizado.'];
        }

        // 4. Caso falte dados
        return ['success' => false, 'mensagem' => 'Dados do signatário não encontrados.'];
    }

    // Método para tratar eventos de fechamento de documento
    private static function documentoFechado($requestData, $acesso, $spa, $dealId, $documentKey, $campoRetorno)
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
            $mensagem = $evento === 'deadline'
                ? 'Assinatura cancelada por prazo finalizado.'
                : 'Assinatura cancelada manualmente.';
            self::atualizarRetornoBitrix(
                ['retorno' => $campoRetorno],
                $spa,
                $dealId,
                $acesso['webhook_bitrix'] ?? null,
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
    private static function documentoDisponivel($requestData, $acesso, $spa, $dealId, $campoArquivoAssinado, $campoRetorno, $documentKey, $webhook)
    {
        LogHelper::logDocumentoAssinado("Início documentoDisponivel | documentKey=$documentKey | spa=$spa | dealId=$dealId | campoArquivoAssinado=$campoArquivoAssinado | campoRetorno=$campoRetorno", 'documentoDisponivel');

        // 1. Buscar status fechado no banco
        $tentativasStatus = 15;
        $esperaStatus = 10;

        $statusClosed = null;
        for ($i = 0; $i < $tentativasStatus; $i++) {
            $statusClosed = AplicacaoAcessoDAO::obterAssinaturaClickSign($documentKey);
            if ($statusClosed !== null) break;
            LogHelper::logDocumentoAssinado("Tentativa $i: statusClosed ainda não encontrado para documentKey=$documentKey", 'documentoDisponivel');
            sleep($esperaStatus);
        }

        if ($statusClosed === null) {
            LogHelper::logDocumentoAssinado("StatusClosed não encontrado após $tentativasStatus tentativas para documentKey=$documentKey", 'documentoDisponivel');
            return ['success' => false, 'mensagem' => 'StatusClosed não encontrado.'];
        }

        // 2. Se for deadline ou cancel, ignora
        if (in_array($statusClosed['status_closed'] ?? '', ['deadline', 'cancel'])) {
            LogHelper::logDocumentoAssinado("Evento {$statusClosed['status_closed']} para documentKey=$documentKey - nada a fazer.", 'documentoDisponivel');
            return ['success' => true, 'mensagem' => "Evento {$statusClosed['status_closed']} ignorado."];
        }

        // 3. Se for auto_close, baixar e anexar
        if (($statusClosed['status_closed'] ?? '') === 'auto_close') {
            $tentativasDownload = 15;
            $esperaDownload = 30;

            for ($j = 0; $j < $tentativasDownload; $j++) {
                LogHelper::logDocumentoAssinado("Tentativa $j para baixar arquivo assinado documentKey=$documentKey", 'documentoDisponivel');

                $retDoc = ClickSignHelper::buscarDocumento($acesso['clicksign_token'], $documentKey);
                $url = $retDoc['document']['downloads']['signed_file_url'] ?? null;
                $nomeArquivo = $retDoc['document']['filename'] ?? "documento_assinado.pdf";

                if ($url) {
                    LogHelper::logDocumentoAssinado("Arquivo disponível para download | url=$url | nomeArquivo=$nomeArquivo", 'documentoDisponivel');

                    // Anexar arquivo ao negócio
                    $resultadoAnexo = BitrixDealHelper::anexarArquivoNegocio(
                        $spa,
                        $dealId,
                        $campoArquivoAssinado,
                        $url,
                        $nomeArquivo
                    );

                    if (isset($resultadoAnexo['success']) && $resultadoAnexo['success']) {
                        self::atualizarRetornoBitrix(
                            ['retorno' => $campoRetorno],
                            $spa,
                            $dealId,
                            $acesso['webhook_bitrix'] ?? null,
                            true,
                            null,
                            mensagemCustomizada: 'Documento assinado e arquivo enviado para o Bitrix.'
                        );

                        LogHelper::logDocumentoAssinado("Arquivo anexado e retorno atualizado no Bitrix para documentKey=$documentKey", 'documentoDisponivel');
                        return ['success' => true, 'mensagem' => 'Arquivo baixado, anexado e mensagem atualizada no Bitrix.'];
                    } else {
                        LogHelper::logDocumentoAssinado("Falha ao anexar arquivo no Bitrix para documentKey=$documentKey", 'documentoDisponivel');
                    }
                } else {
                    LogHelper::logDocumentoAssinado("Arquivo ainda não disponível para download - tentativa " . ($j + 1) . " para documentKey=$documentKey", 'documentoDisponivel');
                }
                sleep($esperaDownload);
            }

            LogHelper::logDocumentoAssinado("Não foi possível baixar/anexar o arquivo após $tentativasDownload tentativas para documentKey=$documentKey", 'documentoDisponivel');
            return ['success' => false, 'mensagem' => 'Não foi possível baixar/anexar o arquivo assinado.'];
        }

        // 4. Status inesperado
        LogHelper::logDocumentoAssinado("StatusClosed inesperado: {$statusClosed['status_closed']} para documentKey=$documentKey", 'documentoDisponivel');

        return ['success' => true, 'mensagem' => 'StatusClosed não tratado.'];
    }


} 
