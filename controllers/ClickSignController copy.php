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

        LogHelper::logClickSign("Início do processo de assinatura", 'controller');

        if (empty($id) || empty($entityId)) {
            LogHelper::logClickSign("Parâmetros obrigatórios ausentes", 'controller');
            $response = ['success' => false, 'mensagem' => 'Parâmetros obrigatórios ausentes.'];
            echo json_encode($response, JSON_UNESCAPED_UNICODE);
            return $response;
        }

        $configExtra = $GLOBALS['ACESSO_AUTENTICADO']['config_extra'] ?? null;
        $configJson = $configExtra ? json_decode($configExtra, true) : [];
        $spaKey = 'SPA_' . $entityId;
        $fields = $configJson[$spaKey]['campos'] ?? [];
        $tokenClicksign = $configJson[$spaKey]['clicksign_token'] ?? null;

        // Adiciona os campos do config ao params
        $params = array_merge($params, $fields);

        if (!$tokenClicksign) {
            LogHelper::logClickSign("Token ClickSign ausente", 'controller');
            self::atualizarRetornoBitrix($params, $entityId, $id, false, null, 'Acesso não autorizado ou incompleto');
            $response = ['success' => false, 'mensagem' => 'Acesso não autorizado ou incompleto.'];
            echo json_encode($response, JSON_UNESCAPED_UNICODE);
            return $response;
        }

        $registro = BitrixDealHelper::consultarDeal($entityId, $id, $fields);
        $dados = $registro['result'] ?? [];

        // Validar se o deal foi encontrado
        if (!isset($dados['id']) || (is_array($dados['id']) && (!isset($dados['id']['valor']) || $dados['id']['valor'] === null))) {
            LogHelper::logClickSign("ERRO - Deal não encontrado | ID: $entityId", 'controller');
            echo json_encode(['success' => false, 'error' => 'Deal não encontrado']);
            return;
        }

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

        // Validar campos essenciais vazios
        $errosValidacao = [];

        // 1. Validar arquivo a ser assinado (obrigatório)
        $arquivoParaAssinar = null;
        if (isset($mapCampos['arquivoaserassinado']) && isset($dados[$mapCampos['arquivoaserassinado']])) {
            $campoArquivo = $dados[$mapCampos['arquivoaserassinado']];
            if (isset($campoArquivo['valor']) && is_array($campoArquivo['valor']) && !empty($campoArquivo['valor'])) {
                $arquivoParaAssinar = $campoArquivo['valor'];
            }
        }
        if (empty($arquivoParaAssinar)) {
            $errosValidacao[] = 'Arquivo a ser assinado é obrigatório';
        }

        // 2. Validar data limite (obrigatória e futura)
        $dataLimite = null;
        if (isset($mapCampos['data']) && isset($dados[$mapCampos['data']])) {
            $dataLimite = $dados[$mapCampos['data']]['valor'] ?? null;
        }
        if (empty($dataLimite)) {
            $errosValidacao[] = 'Data limite de assinatura é obrigatória';
        } else {
            // Extrair apenas a data (remover hora e fuso)
            $dataLimiteFormatada = substr($dataLimite, 0, 10);
            $dataAtual = date('Y-m-d');
            if ($dataLimiteFormatada <= $dataAtual) {
                $errosValidacao[] = 'Data limite deve ser posterior à data atual';
            }
        }
 
        // 3. Validar pelo menos um signatário (contratante, contratada ou testemunha)
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
            $errosValidacao[] = 'Pelo menos um signatário deve estar configurado (contratante, contratada ou testemunha)';
        }

        // Se há erros de validação, reportar e parar
        if (!empty($errosValidacao)) {
            $mensagemErro = 'Campos obrigatórios ausentes ou inválidos: ' . implode(', ', $errosValidacao);
            LogHelper::logClickSign("ERRO - $mensagemErro", 'controller');
            self::atualizarRetornoBitrix($params, $entityId, $id, false, null, $mensagemErro);
            echo json_encode(['success' => false, 'error' => $mensagemErro]);
            return;
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
            ['ID', 'NAME', 'LAST_NAME', 'EMAIL']
        );

        $signatarios = [
            'contratante' => [],
            'contratada'  => [],
            'testemunha'  => [],
        ];

        $todosSignatariosParaJson = [];
        $erroSignatario = false;
        $erroMensagem = '';
        $qtdSignatarios = 0;

        foreach ($contatosConsultados as $papel => $listaContatos) {
            foreach ($listaContatos as $contato) {
                $idContato = $contato['ID'] ?? null;
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

                if (!$idContato || !$nome || !$sobrenome || !$email) {
                    $erroSignatario = true;
                    $erroMensagem = "Dados faltantes nos signatários ($papel)";
                    break 2;
                }

                $signatarioInfo = [
                    'nome' => $nome,
                    'sobrenome' => $sobrenome,
                    'email' => $email,
                ];

                $signatarios[$papel][] = $signatarioInfo;

                $todosSignatariosParaJson[] = array_merge(['id' => $idContato], $signatarioInfo);

                $qtdSignatarios++;
            }
        }

        if ($erroSignatario) {
            LogHelper::logClickSign("Erro: $erroMensagem", 'controller');
            self::atualizarRetornoBitrix($params, $entityId, $id, false, null, $erroMensagem);
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

        // Validação do tipo de arquivo
        if ($arquivoConvertido) {
            $extensoesPermitidas = ['pdf', 'docx', 'doc', 'png', 'jpg', 'jpeg'];
            $extensaoArquivo = strtolower($arquivoConvertido['extensao']);
            if (!in_array($extensaoArquivo, $extensoesPermitidas)) {
                $mensagemErro = 'Tipo de arquivo inválido. Apenas (' . implode(', ', $extensoesPermitidas) . ') são permitidos.';
                LogHelper::logClickSign("ERRO - $mensagemErro", 'controller');
                self::atualizarRetornoBitrix($params, $entityId, $id, false, null, $mensagemErro);
                echo json_encode(['success' => false, 'error' => $mensagemErro]);
                exit;
            }
        }

        if (!$arquivoConvertido) {
            LogHelper::logClickSign("Erro ao converter o arquivo", 'controller');
            self::atualizarRetornoBitrix($params, $entityId, $id, false, null, 'Erro ao converter o arquivo');
            $response = ['success' => false, 'mensagem' => 'Erro ao converter o arquivo.'];
            echo json_encode($response, JSON_UNESCAPED_UNICODE);
            exit;
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
        $retornoClickSign = ClickSignHelper::criarDocumento($payloadClickSign);

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

                    $retornoSignatario = ClickSignHelper::criarSignatario([
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

                    $vinculo = ClickSignHelper::vincularSignatario([
                        'document_key' => $documentKey,
                        'signer_key'   => $signerKey,
                        'sign_as'      => $signAs,
                        'message'      => "Prezado(a) $papel,\nPor favor assine o documento.\n\nAtenciosamente,\nKWCA"
                    ]);

                    
                    if (!empty($vinculo['list']['request_signature_key'])) {
                        $mensagem = "Prezado(a), segue documento para assinatura.";
                        ClickSignHelper::enviarNotificacao($vinculo['list']['request_signature_key'], $mensagem);
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
                    $jsonSignatarios = json_encode($todosSignatariosParaJson, JSON_UNESCAPED_UNICODE);
                    LogHelper::logClickSign("JSON de Signatarios para o DAO: " . $jsonSignatarios, 'controller');

                    AplicacaoAcessoDAO::registrarAssinaturaClicksign([
                        'document_key'               => $documentKey,
                        'cliente_id'                 => $clienteId,
                        'deal_id'                    => $id,
                        'spa'                        => $entityId,
                        'Signatarios'                => $jsonSignatarios,
                        'campo_contratante'          => $params['contratante'] ?? null,
                        'campo_contratada'           => $params['contratada'] ?? null,
                        'campo_testemunhas'          => $params['testemunhas'] ?? null,
                        'campo_data'                 => $params['data'] ?? null,
                        'campo_arquivoaserassinado'  => $params['arquivoaserassinado'] ?? null,
                        'campo_arquivoassinado'      => $params['arquivoassinado'] ?? null,
                        'campo_idclicksign'          => $params['idclicksign'] ?? null,
                        'campo_retorno'              => $params['retorno'] ?? null,
                        'etapa_concluida'            => $params['EtapaConcluido'] ?? null
                    ]);
                    $gravado = true;
                } catch (PDOException $e) {
                    $tentativas++;
                    if ($tentativas < $maxTentativas) sleep(10);
                }
            }

            if ($gravado) {
                // Início: Lógica para popular os campos de signatários no Bitrix
                $campoSignatariosAssinar = $params['signatarios_assinar'] ?? null;
                $campoSignatariosAssinaram = $params['signatarios_assinaram'] ?? null;

                if ($campoSignatariosAssinar && $campoSignatariosAssinaram) {
                    $idsSignatarios = array_column($todosSignatariosParaJson, 'id');
                    
                    $fieldsUpdate = [
                        $campoSignatariosAssinar => $idsSignatarios,
                        $campoSignatariosAssinaram => '' // Limpa o campo de quem já assinou
                    ];
                    
                    BitrixDealHelper::editarDeal($entityId, $id, $fieldsUpdate);
                }
                // Fim: Lógica para popular os campos de signatários

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
                $mensagemErro = "Erro ao registrar assinatura no banco de dados. AVISO: Os retornos automáticos (quem assinou, documento finalizado) não funcionarão. O acompanhamento deverá ser manual. Entre em contato com o suporte de TI.";
                self::atualizarRetornoBitrix($params, $entityId, $id, false, null, $mensagemErro);
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

        $mensagemRetorno = $sucesso ?
            ($mensagemCustomizada ?? "Documento enviado para assinatura") :
            ($mensagemCustomizada ?? "Erro no envio do documento para assinatura");

        $fields = [];
        $mensagemFinalTrigger = 'Documento assinado e arquivo anexado com sucesso.';

        // Condição para atualizar o campo de retorno: apenas para a mensagem final ou em caso de erro.
        if ($campoRetorno && (!$sucesso || $mensagemCustomizada === $mensagemFinalTrigger)) {
            // Se for a mensagem de gatilho, usa o código. Senão, a mensagem de erro.
            $fields[$campoRetorno] = ($mensagemCustomizada === $mensagemFinalTrigger) ? 'CS405' : $mensagemRetorno;
        }

        // A atualização do ID da ClickSign na criação do documento deve sempre ocorrer.
        if ($sucesso && $campoIdClickSign && $documentKey) {
            $fields[$campoIdClickSign] = $documentKey;
        }

        $response = ['success' => true];

        if (!empty($fields)) {
            $response = BitrixDealHelper::editarDeal($spa, $dealId, $fields);
            if (!isset($response['status']) || $response['status'] !== 'sucesso') {
                LogHelper::logClickSign("ERRO ao editar deal em atualizarRetornoBitrix | spa: $spa | dealId: $dealId | fields: " . json_encode($fields) . " | response: " . json_encode($response), 'atualizarRetornoBitrix');
            }
        }

        // O comentário na timeline é sempre adicionado.
        $comentario = "Retorno ClickSign: " . $mensagemRetorno;
        if ($documentKey) {
            $comentario .= "\nDocumento ID: " . $documentKey;
        }
        BitrixDealHelper::adicionarComentarioDeal($spa, $dealId, $comentario);

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

        // 3. Validação HMAC - busca secret do config_extra
        $configExtra = $GLOBALS['ACESSO_AUTENTICADO']['config_extra'] ?? null;
        $configJson = $configExtra ? json_decode($configExtra, true) : [];
        $dadosAssinatura = AplicacaoAcessoDAO::obterAssinaturaClickSign($documentKey);
        $spa = $dadosAssinatura['spa'] ?? null;
        $spaKey = 'SPA_' . $spa;
        $secret = $configJson[$spaKey]['clicksign_secret'] ?? null;
        $token = $configJson[$spaKey]['clicksign_token'] ?? null; // Carrega o token aqui
        $headerSignature = $_SERVER['HTTP_CONTENT_HMAC'] ?? null;

        // Validação de credenciais (sem log positivo)
        if (empty($secret) || empty($token)) {
            LogHelper::logClickSign("ERRO: Token ou Secret não configurados para a SPA: $spaKey", 'controller');
            return ['success' => false, 'mensagem' => 'Credenciais de API não configuradas.'];
        }

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
        LogHelper::logClickSign("Webhook recebido: $evento | Documento: $documentKey", 'controller');

        switch ($evento) {
            case 'sign':
                $assinante = $requestData['event']['data']['signer']['email'] ?? null;
                if (!empty($dadosAssinatura['assinatura_processada']) && strpos($dadosAssinatura['assinatura_processada'], $assinante) !== false) {
                    return ['success' => true, 'mensagem' => 'Assinatura já processada.'];
                }
                AplicacaoAcessoDAO::salvarStatus($documentKey, null, ($dadosAssinatura['assinatura_processada'] ?? '') . ";" . $assinante);
                return self::assinaturaRealizada($requestData, $spa, $dealId, $campoRetorno);

            case 'deadline':
            case 'cancel':
            case 'auto_close':
                if (!empty($dadosAssinatura['documento_fechado_processado'])) {
                    return ['success' => true, 'mensagem' => 'Evento de documento fechado já processado.'];
                }
                AplicacaoAcessoDAO::salvarStatus($documentKey, $evento, null, true);
                return self::documentoFechado($requestData, $spa, $dealId, $documentKey, $campoRetorno);

            case 'document_closed':
                if (!empty($dadosAssinatura['documento_disponivel_processado'])) {
                    return ['success' => true, 'mensagem' => 'Documento já disponível e processado anteriormente.'];
                }
                AplicacaoAcessoDAO::salvarStatus($documentKey, null, null, null, true);
                return self::documentoDisponivel($requestData, $spa, $dealId, $campoArquivoAssinado, $campoRetorno, $documentKey, $token);

            default:
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

        // 2. Extrai signatário e document key do evento
        $signer = $requestData['event']['data']['signer'] ?? null;
        $documentKey = $requestData['document']['key'] ?? null;

        // 3. Se encontrou signatário, processa a lógica de movimentação
        if ($signer && $documentKey) {
            $nome  = $signer['name']  ?? '';
            $email = $signer['email'] ?? '';

            if (empty($nome) || empty($email)) {
                LogHelper::logClickSign("ERRO: Dados do signatário incompletos | nome: $nome | email: $email", 'assinaturaRealizada');
                return ['success' => false, 'mensagem' => 'Dados do signatário incompletos.'];
            }

            // Lógica de movimentação de signatários
            $configExtra = $GLOBALS['ACESSO_AUTENTICADO']['config_extra'] ?? null;
            $configJson = $configExtra ? json_decode($configExtra, true) : [];
            $spaKey = 'SPA_' . $spa;
            $campos = $configJson[$spaKey]['campos'] ?? [];
            $campoSignatariosAssinar = $campos['signatarios_assinar'] ?? null;
            $campoSignatariosAssinaram = $campos['signatarios_assinaram'] ?? null;

            if ($campoSignatariosAssinar && $campoSignatariosAssinaram) {
                $dadosAssinatura = AplicacaoAcessoDAO::obterAssinaturaClickSign($documentKey);
                $signatariosJson = $dadosAssinatura['Signatarios'] ?? '[]';
                $todosSignatarios = json_decode($signatariosJson, true);
                
                $assinaturasProcessadas = array_filter(explode(';', $dadosAssinatura['assinatura_processada'] ?? ''));

                $idsAssinaram = [];
                $idsTodos = [];

                foreach ($todosSignatarios as $s) {
                    $idsTodos[] = $s['id'];
                    // Compara o e-mail do signatário atual com a lista de e-mails já processados
                    if (in_array($s['email'], $assinaturasProcessadas)) {
                        $idsAssinaram[] = $s['id'];
                    }
                }
                
                $idsAAssinar = array_diff($idsTodos, $idsAssinaram);
                // Monta o payload para a atualização
                $fieldsUpdate = [
                    $campoSignatariosAssinaram => array_values($idsAssinaram)
                ];
                if (empty($idsAAssinar)) {
                    $fieldsUpdate[$campoSignatariosAssinar] = '';
                } else {
                    $fieldsUpdate[$campoSignatariosAssinar] = array_values($idsAAssinar);
                }

                BitrixDealHelper::editarDeal($spa, $dealId, $fieldsUpdate);
            }

            // 4. Atualiza status no Bitrix (comentário e campo de retorno)
            $mensagem = "Assinatura feita por $nome - $email";
            $resultado = self::atualizarRetornoBitrix(
                ['retorno' => $campoRetorno],
                $spa,
                $dealId,
                true,
                $documentKey,
                $mensagem
            );

            if (isset($resultado['status']) && $resultado['status'] === 'sucesso') {
                return ['success' => true, 'mensagem' => 'Assinatura processada e campos atualizados.'];
            } else {
                $erroDetalhado = json_encode($resultado);
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
                $documentKey,
                $mensagem
            );
            return ['success' => true, 'mensagem' => "Evento $evento processado com atualização imediata no Bitrix."];
        }

    // 3. Se auto_close: apenas salva o status e aguarda o document_closed para baixar o arquivo e enviar a mensagem final.
    if ($evento === 'auto_close') {
        return ['success' => true, 'mensagem' => 'Evento auto_close salvo. Aguardando document_closed para baixar o arquivo.'];
    }

        // 4. Outros eventos (não esperado)
        LogHelper::logClickSign("Evento de fechamento não tratado: $evento", 'controller');
        return ['success' => true, 'mensagem' => "Evento de fechamento $evento ignorado."];
    }

    // Método para tratar eventos Documento disponível para download
    private static function documentoDisponivel($requestData, $spa, $dealId, $campoArquivoAssinado, $campoRetorno, $documentKey, $token)
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
                ], $spa, $dealId, true, $documentKey, 'Documento assinado com sucesso.');

                if (isset($resultadoMensagem['status']) && $resultadoMensagem['status'] === 'sucesso') {
                    return ['success' => true, 'mensagem' => 'Mensagem final enviada (sem anexo de arquivo).'];
                } else {
                    $erroDetalhado = json_encode($resultadoMensagem);
                    LogHelper::logClickSign("ERRO: Falha ao enviar mensagem final | erro: $erroDetalhado", 'documentoDisponivel');
                    return ['success' => false, 'mensagem' => 'Falha ao enviar mensagem final.'];
                }
            }

            // 4.2. Processar download e anexo do arquivo
            $url = $requestData['document']['downloads']['signed_file_url'] ?? null;
            $nomeArquivo = $requestData['document']['filename'] ?? "documento_assinado.pdf";

            if ($url) {
                LogHelper::logClickSign("URL do arquivo assinado encontrada no webhook. Tentando baixar.", 'documentoDisponivel');
                
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

                // 4.2.2. Prepara estrutura do campo para o Bitrix (comportamento padrão: anexa apenas o novo arquivo)
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

                    if (isset($resultado['status']) && $resultado['status'] === 'sucesso') {
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
                        ], $spa, $dealId, true, $documentKey, 'Documento assinado e arquivo anexado com sucesso.');

                        // Início da lógica de limpeza de campos
                        $configExtra = $GLOBALS['ACESSO_AUTENTICADO']['config_extra'] ?? null;
                        $configJson = $configExtra ? json_decode($configExtra, true) : [];
                        $spaKey = 'SPA_' . $spa;
                        $camposConfig = $configJson[$spaKey]['campos'] ?? [];
                        
                        $fieldsLimpeza = [
                            $camposConfig['contratante']           => '',
                            $camposConfig['contratada']            => '',
                            $camposConfig['testemunhas']           => '',
                            $camposConfig['signatarios_assinar']   => '',
                            $camposConfig['signatarios_assinaram'] => '',
                            $camposConfig['arquivoaserassinado']   => '',
                            $camposConfig['data']                  => '',
                            $camposConfig['idclicksign']           => ''
                        ];

                        BitrixDealHelper::editarDeal($spa, $dealId, $fieldsLimpeza);
                        // Fim da lógica de limpeza

                        // Início da lógica para mudança de etapa
                        $etapaConcluidaNome = $statusClosed['etapa_concluida'] ?? null;
                        if ($etapaConcluidaNome) {
                            $etapas = BitrixHelper::consultarEtapasPorTipo($spa);
                            $statusIdAlvo = null;
                            foreach ($etapas as $etapa) {
                                if (isset($etapa['NAME']) && strtolower($etapa['NAME']) === strtolower($etapaConcluidaNome)) {
                                    $statusIdAlvo = $etapa['STATUS_ID'];
                                    break;
                                }
                            }

                            if ($statusIdAlvo) {
                                // Revertido: Apenas move a etapa, sem tentar alterar o autor
                                BitrixDealHelper::editarDeal($spa, $dealId, ['stageId' => $statusIdAlvo]);
                                LogHelper::logClickSign("Deal $dealId movido para a etapa '$etapaConcluidaNome' ($statusIdAlvo)", 'documentoDisponivel');
                            } else {
                                LogHelper::logClickSign("AVISO: Etapa '$etapaConcluidaNome' não encontrada para a SPA $spa. O deal não foi movido.", 'documentoDisponivel');
                            }
                        }
                        // Fim da lógica para mudança de etapa

                        if (isset($resultadoMensagem['status']) && $resultadoMensagem['status'] === 'sucesso') {
                            LogHelper::logClickSign("Processo de assinatura finalizado com sucesso | Documento: $documentKey", 'controller');
                            return ['success' => true, 'mensagem' => 'Arquivo baixado, anexado e mensagem atualizada no Bitrix.'];
                        } else {
                        $erroDetalhado = json_encode($resultadoMensagem);
                        LogHelper::logClickSign("ERRO: Falha ao atualizar mensagem final no Bitrix | erro: $erroDetalhado", 'documentoDisponivel');
                        return ['success' => false, 'mensagem' => 'Arquivo anexado, mas falha ao atualizar mensagem no Bitrix.'];
                    }
                } else {
                    LogHelper::logClickSign("ERRO: Falha ao anexar arquivo após $tentativasAnexo tentativas | último erro: $ultimoErro", 'documentoDisponivel');
                    return ['success' => false, 'mensagem' => "Erro ao anexar arquivo no Bitrix após $tentativasAnexo tentativas: $ultimoErro"];
                }
            } else {
                LogHelper::logClickSign("ERRO: URL do arquivo assinado não encontrada no corpo do webhook 'document_closed'.", 'documentoDisponivel');
                return ['success' => false, 'mensagem' => 'URL de download não encontrada no webhook.'];
            }
        }

        // 5. Status inesperado
        LogHelper::logClickSign("AVISO: Status inesperado encontrado | status: " . ($statusClosed['status_closed'] ?? 'null'), 'documentoDisponivel');
        return ['success' => true, 'mensagem' => 'StatusClosed não tratado.'];
    }

    public static function atualizarDocumentoClickSign()
    {
        header('Content-Type: application/json; charset=utf-8');
        $params = $_GET;
        $action = $params['action'] ?? null;
        $entityId = $params['spa'] ?? null;
        $id = $params['deal'] ?? null;

        LogHelper::logClickSign("Início do processo de atualização/cancelamento: Ação '$action'", 'controller');

        if (empty($id) || empty($entityId) || empty($action)) {
            $mensagem = 'Parâmetros obrigatórios (deal, spa, action) ausentes.';
            LogHelper::logClickSign($mensagem, 'controller');
            echo json_encode(['success' => false, 'mensagem' => $mensagem], JSON_UNESCAPED_UNICODE);
            return;
        }

        // Obter configurações e token
        $configExtra = $GLOBALS['ACESSO_AUTENTICADO']['config_extra'] ?? null;
        $configJson = $configExtra ? json_decode($configExtra, true) : [];
        $spaKey = 'SPA_' . $entityId;
        $fieldsConfig = $configJson[$spaKey]['campos'] ?? [];
        $tokenClicksign = $configJson[$spaKey]['clicksign_token'] ?? null;

        if (!$tokenClicksign) {
            $mensagem = 'Acesso não autorizado ou incompleto.';
            LogHelper::logClickSign($mensagem, 'controller');
            self::atualizarRetornoBitrix($params, $entityId, $id, false, null, $mensagem);
            echo json_encode(['success' => false, 'mensagem' => $mensagem], JSON_UNESCAPED_UNICODE);
            return;
        }

        // Consultar o Deal para obter o ID do documento na ClickSign (Correção: sem formatação prévia)
        $campoIdClickSignOriginal = $fieldsConfig['idclicksign'] ?? null;
        if (empty($campoIdClickSignOriginal)) {
            $mensagem = 'Campo "idclicksign" não configurado para esta SPA.';
            LogHelper::logClickSign($mensagem, 'controller');
            echo json_encode(['success' => false, 'mensagem' => $mensagem], JSON_UNESCAPED_UNICODE);
            return;
        }
        
        $dealData = BitrixDealHelper::consultarDeal($entityId, $id, [$campoIdClickSignOriginal]);
        $campoIdClickSignFormatado = array_key_first(BitrixHelper::formatarCampos([$campoIdClickSignOriginal => null]));
        $documentKey = $dealData['result'][$campoIdClickSignFormatado]['valor'] ?? null;

        if (empty($documentKey)) {
            // Silenciosamente ignora a requisição se o document_key não existir.
            // Isso evita erros em cénarios de disparo múltiplo de gatilhos.
            LogHelper::logClickSign("Ação de atualização ignorada: document_key não encontrado no Deal $id. Provavelmente um disparo de gatilho concorrente.", 'controller');
            echo json_encode(['success' => true, 'mensagem' => 'Ação ignorada, nenhum documento para atualizar.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        switch ($action) {
            case 'Cancelar Documento':
                $resultado = ClickSignHelper::cancelarDocumento($documentKey);
                if (isset($resultado['document'])) {
                    $mensagem = "Documento ($documentKey) cancelado com sucesso.";
                    self::atualizarRetornoBitrix($params, $entityId, $id, true, $documentKey, $mensagem);
                    echo json_encode(['success' => true, 'mensagem' => $mensagem], JSON_UNESCAPED_UNICODE);
                } else {
                    $erro = $resultado['errors'][0] ?? 'Erro desconhecido ao cancelar.';
                    $mensagem = "Falha ao cancelar documento ($documentKey): $erro";
                    self::atualizarRetornoBitrix($params, $entityId, $id, false, $documentKey, $mensagem);
                    echo json_encode(['success' => false, 'mensagem' => $mensagem, 'details' => $resultado], JSON_UNESCAPED_UNICODE);
                }
                break;

            case 'Atualizar Documento':
                // Lógica para atualizar a data (Correção: sem formatação prévia)
                $campoDataOriginal = $fieldsConfig['data'] ?? null;
                 if (empty($campoDataOriginal)) {
                    $mensagem = 'Campo "data" não configurado para esta SPA.';
                    LogHelper::logClickSign($mensagem, 'controller');
                    echo json_encode(['success' => false, 'mensagem' => $mensagem], JSON_UNESCAPED_UNICODE);
                    return;
                }

                $dealData = BitrixDealHelper::consultarDeal($entityId, $id, [$campoDataOriginal]);
                $campoDataFormatado = array_key_first(BitrixHelper::formatarCampos([$campoDataOriginal => null]));
                $novaData = $dealData['result'][$campoDataFormatado]['valor'] ?? null;

                if (empty($novaData)) {
                    $mensagem = 'Campo de data não encontrado ou vazio no Deal.';
                    echo json_encode(['success' => false, 'mensagem' => $mensagem], JSON_UNESCAPED_UNICODE);
                    return;
                }
                
                $novaDataFormatada = substr($novaData, 0, 10); // Formato YYYY-MM-DD

                $payload = ['document' => ['deadline_at' => $novaDataFormatada]];
                $resultado = ClickSignHelper::atualizarDocumento($documentKey, $payload);

                if (isset($resultado['document'])) {
                    $mensagem = "Data do documento atualizada para $novaDataFormatada.";
                    self::atualizarRetornoBitrix($params, $entityId, $id, true, $documentKey, $mensagem);
                    echo json_encode(['success' => true, 'mensagem' => $mensagem], JSON_UNESCAPED_UNICODE);
                } else {
                    $erro = $resultado['errors'][0] ?? 'Erro desconhecido ao atualizar data.';
                    $mensagem = "Falha ao atualizar data do documento: $erro";
                    self::atualizarRetornoBitrix($params, $entityId, $id, false, $documentKey, $mensagem);
                    echo json_encode(['success' => false, 'mensagem' => $mensagem, 'details' => $resultado], JSON_UNESCAPED_UNICODE);
                }
                break;

            default:
                $mensagem = "Ação '$action' é inválida. Use 'Cancelar Documento' ou 'Atualizar Documento'.";
                LogHelper::logClickSign($mensagem, 'controller');
                echo json_encode(['success' => false, 'mensagem' => $mensagem], JSON_UNESCAPED_UNICODE);
                break;
        }
    }
}
