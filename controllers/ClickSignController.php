<?php
// Requisições dos helpers e DAOs necessários

require_once __DIR__ . '/../helpers/BitrixDealHelper.php';
require_once __DIR__ . '/../helpers/ClickSignHelper.php';
require_once __DIR__ . '/../helpers/LogHelper.php';
require_once __DIR__ . '/../dao/AplicacaoAcessoDAO.php';

use dao\AplicacaoAcessoDAO;

class ClickSignController
{
    public function GerarAssinatura()
    {
        $dados = $_GET;

        LogHelper::logClickSign("Início do método GerarAssinatura", 'controller');


        $cliente = $dados['cliente'] ?? null;
        $dealId = $dados['deal'] ?? null;
        $spa = $dados['spa'] ?? null;

        if (empty($cliente) || empty($dealId) || empty($spa)) {
            LogHelper::logClickSign("Parâmetros obrigatórios ausentes.", 'controller');
            return ['success' => false, 'mensagem' => 'Parâmetros obrigatórios ausentes.'];
        }

        $acesso = AplicacaoAcessoDAO::obterWebhookPermitido($cliente, 'clicksign');
        if (!$acesso || empty($acesso['webhook_bitrix']) || empty($acesso['clicksign_token'])) {
            LogHelper::logClickSign("Acesso à aplicação ClickSign não autorizado ou incompleto.", 'controller');
            return ['success' => false, 'mensagem' => 'Acesso não autorizado ou incompleto.'];
        }

        $webhook = $acesso['webhook_bitrix'];
        $tokenClicksign = $acesso['clicksign_token'];

        LogHelper::logClickSign("Webhook obtido: {$webhook}", 'controller');
        LogHelper::logClickSign("Token obtido.", 'controller');

        // Monta filtro para consultar negócio via helper
        $filtrosConsulta = [
            'spa' => (int)$spa,
            'deal' => (int)$dealId,
            'webhook' => $webhook,
            'campos' => $dados['arquivoaserassinado'] ?? ''
        ];

        // Consulta o negócio pelo helper
        $negociacao = BitrixDealHelper::consultarNegociacao($filtrosConsulta);

        if (!isset($negociacao['result']['item'])) {
            LogHelper::logClickSign("Negócio não encontrado ou erro na consulta.", 'controller');
            return ['success' => false, 'mensagem' => 'Negócio não encontrado ou erro na consulta.'];
        }

        $campos = $negociacao['result']['item'];

        LogHelper::logClickSign("Campos personalizados extraídos: " . json_encode($campos), 'controller');


        // Extrai o campo do arquivo formatado
        $campoArquivoFormatado = BitrixHelper::formatarCampos([$dados['arquivoaserassinado'] ?? ''])[$dados['arquivoaserassinado'] ?? ''] ?? strtoupper($dados['arquivoaserassinado'] ?? '');

        if (empty($campos[$campoArquivoFormatado])) {
            LogHelper::logClickSign("Campo do arquivo não encontrado no negócio.", 'controller');
            return ['success' => false, 'mensagem' => 'Campo do arquivo não encontrado no negócio.'];
        }

        $arquivoInfo = reset($campos[$campoArquivoFormatado]);
        $urlArquivo = $arquivoInfo['url'] ?? null;
        $nomeArquivo = $arquivoInfo['name'] ?? null;


        if (empty($urlArquivo) || empty($nomeArquivo)) {
            LogHelper::logClickSign("URL ou nome do arquivo inválidos.", 'controller');
            return ['success' => false, 'mensagem' => 'URL ou nome do arquivo inválidos.'];
        }

        LogHelper::logClickSign("URL do arquivo: {$urlArquivo}", 'controller');
        LogHelper::logClickSign("Nome do arquivo: {$nomeArquivo}", 'controller');

        // Baixa e converte arquivo para base64
        $conteudo = @file_get_contents($urlArquivo);

        LogHelper::logClickSign("Tentando baixar arquivo da URL: " . $urlArquivo, 'controller');

        if ($conteudo === false) {
            LogHelper::logClickSign("Falha ao baixar o arquivo da URL.", 'controller');
            return ['success' => false, 'mensagem' => 'Falha ao baixar o arquivo da URL.'];
        }
        $base64Arquivo = base64_encode($conteudo);

        LogHelper::logClickSign("Arquivo convertido para base64 com sucesso.", 'controller');

        // Monta payload para ClickSign
        $payloadClickSign = [
            'document' => [
                'content_base64' => $base64Arquivo,
                'filename'       => $nomeArquivo,
                'path'           => '/',
                'description'    => 'Documento gerado via integração'
            ]
        ];

        LogHelper::logClickSign("Payload montado para ClickSign.", 'controller');

        // Cria documento na ClickSign
        $retornoClickSign = ClickSignHelper::criarDocumento($payloadClickSign, $tokenClicksign);

        LogHelper::logClickSign("Resposta ClickSign: " . json_encode($retornoClickSign), 'controller');

        return $retornoClickSign;
    }
}
