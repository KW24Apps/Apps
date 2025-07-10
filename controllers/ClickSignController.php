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

            LogHelper::logClickSign("Vínculo finalizado | Cliente: $cliente | DocumentKey: $documentKey | Total vínculos: $qtdVinculos", 'controller');

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
        BitrixDealHelper::editarNegociacao($dados);
    }

    public static function processarAssinaturas($requestData)
    {
        // Captura o cliente da URL
        $cliente = $_GET['cliente'] ?? null; // Cliente vindo da URL
        $documentKey = $requestData['document']['key'] ?? null; // document.key do JSON da ClickSign
        $secret = null;  // Inicializa o secret como nulo, pois ele será recuperado do banco

        LogHelper::logClickSign("Início ProcessarAssinaturas | Cliente: $cliente | DocumentKey: $documentKey", 'controller');

        // Valida o cliente e verifica se ele está ativo no banco
        if (empty($cliente) || empty($documentKey)) {
            LogHelper::logClickSign("Parâmetros obrigatórios ausentes | Cliente: $cliente | DocumentKey: $documentKey", 'controller');
            return ['success' => false, 'mensagem' => 'Parâmetros obrigatórios ausentes.'];
        }

        // Consultar no banco para obter o webhook e o secret
        $acesso = AplicacaoAcessoDAO::obterWebhookPermitido($cliente, 'clicksign');
        if (!$acesso || empty($acesso['clicksign_secret'])) {
            LogHelper::logClickSign("Acesso não autorizado ou Secret não encontrado | Cliente: $cliente", 'controller');
            return ['success' => false, 'mensagem' => 'Acesso não autorizado ou Secret não encontrado.'];
        }

        // Agora que temos o secret, vamos validar o HMAC
        $secret = $acesso['clicksign_secret'];  // Pega o secret configurado no banco
        $receivedSignature = $_SERVER['HTTP_X_SIGNATURE'];  // Cabeçalho x-signature enviado pela ClickSign
        $body = file_get_contents('php://input');  // Corpo da requisição

        // Gerar o HMAC usando o secret
        $calculatedSignature = hash_hmac('sha256', $body, $secret);

        // Log para comparar a assinatura calculada com a recebida
        LogHelper::logClickSign("Assinatura calculada: $calculatedSignature | Assinatura recebida: $receivedSignature", 'controller');

        // Comparar o HMAC calculado com o HMAC recebido
        if ($receivedSignature !== $calculatedSignature) {
            LogHelper::logClickSign("Assinatura HMAC inválida | Cliente: $cliente", 'controller');
            return ['success' => false, 'mensagem' => 'Assinatura HMAC inválida.'];
        }

        // Consultar os dados de assinatura na tabela "assinaturas_clicksign"
        $dadosAssinatura = AplicacaoAcessoDAO::obterCamposAssinatura($documentKey);
        if (!$dadosAssinatura) {
            LogHelper::logClickSign("Documento não encontrado | Cliente: $cliente | DocumentKey: $documentKey", 'controller');
            return ['success' => false, 'mensagem' => 'Documento não encontrado.'];
        }

        // Extraindo campos de retorno
        $campoArquivoAssinado = $dadosAssinatura['campo_arquivoassinado'] ?? null;
        $campoRetorno = $dadosAssinatura['campo_retorno'] ?? null;

        if (!$campoArquivoAssinado || !$campoRetorno) {
            LogHelper::logClickSign("Campos necessários não encontrados | Cliente: $cliente | DocumentKey: $documentKey", 'controller');
            return ['success' => false, 'mensagem' => 'Campos necessários não encontrados na assinatura.'];
        }

        // Atualizar o status no Bitrix
        self::atualizarRetornoBitrix($requestData, $acesso['spa'], $requestData['deal'], $acesso['webhook_bitrix'], true, $documentKey);

        return ['success' => true, 'mensagem' => 'Assinatura processada com sucesso.'];
    }








} 
