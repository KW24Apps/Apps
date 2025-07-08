<?php
require_once __DIR__ . '/../helpers/BitrixHelper.php';
class BitrixDealHelper

{
    // Cria um negócio no Bitrix24 via API
    public static function criarNegocio($dados): array 
    {
        //$dados = $_POST ?: $_GET;
        $spa = $dados['spa'] ?? null;
        $categoryId = $dados['CATEGORY_ID'] ?? null;
        $webhook = $dados['webhook'] ?? null;

        unset($dados['cliente'], $dados['spa'], $dados['CATEGORY_ID'], $dados['webhook']);

        $fields = BitrixHelper::formatarCampos($dados);
        if ($categoryId) {
            $fields['categoryId'] = $categoryId;
        }

        $params = [
            'entityTypeId' => $spa,
            'fields' => $fields
        ];

        $resultado = BitrixHelper::chamarApi('crm.item.add', $params, [
            'webhook' => $webhook,
            'log' => true
        ]);

        if (isset($resultado['result']['item']['id'])) {
            return [
                'success' => true,
                'id' => $resultado['result']['item']['id']
            ];
        }

        return [
            'success' => false,
            'debug' => $resultado,
            'error' => $resultado['error_description'] ?? 'Erro desconhecido ao criar negócio.'
        ];
    }

    // Edita um negócio existente no Bitrix24 via API
    public static function editarNegociacao($dados = []): array
    {
        $spa = $dados['spa'] ?? null;
        $dealId = $dados['deal'] ?? null;
        $webhook = $dados['webhook'] ?? null;

        unset($dados['cliente'], $dados['spa'], $dados['deal'], $dados['webhook']);

        if (!$spa || !$dealId || empty($dados)) {
            return [
                'success' => false,
                'error' => 'Parâmetros obrigatórios não informados.'
            ];
        }

        $fields = BitrixHelper::formatarCampos($dados);

        $params = [
            'entityTypeId' => $spa,
            'id' => (int)$dealId,
            'fields' => $fields
        ];

        $resultado = BitrixHelper::chamarApi('crm.item.update', $params, [
            'webhook' => $webhook,
            'log' => true
        ]);

        if (isset($resultado['result'])) {
            return [
                'success' => true,
                'id' => $dealId
            ];
        }

        return [
            'success' => false,
            'debug' => $resultado,
            'error' => $resultado['error_description'] ?? 'Erro desconhecido ao editar negócio.'
        ];
    }

    // Consulta uma Negócio específico no Bitrix24 via ID
    public static function consultarNegociacao($filtros): array
    {
        LogHelper::logBitrixDealHelpers("Iniciando consultarNegociacao com filtros: " . json_encode($filtros), 'consultarNegociacao');

        $spa = $filtros['spa'] ?? 0;
        $dealId = $filtros['deal'] ?? null;
        $webhook = $filtros['webhook'] ?? null;

        if (!$dealId || !$webhook) {
            return ['erro' => 'ID do negócio ou webhook não informado.'];
        }

        $select = ['id'];

        if (!empty($filtros['campos'])) {
            // Usa formatarCampos para converter os campos
            $camposFormatados = BitrixHelper::formatarCampos(array_map('trim', explode(',', $filtros['campos'])));

            foreach ($camposFormatados as $campoFormatado) {
                if (!in_array($campoFormatado, $select)) {
                    $select[] = $campoFormatado;
                }
            }
        }

        $params = [
            'entityTypeId' => $spa,
            'id' => (int)$dealId,
            'select' => $select
        ];

        $resultado = BitrixHelper::chamarApi('crm.item.get', $params, [
            'webhook' => $webhook,
            'log' => false
        ]);

        LogHelper::logBitrixDealHelpers("Resposta da API Bitrix para consultarNegociacao: " . json_encode($resultado), 'consultarNegociacao');

        if (!isset($resultado['result']['item'])) {
            return $resultado;
        }

        $item = $resultado['result']['item'];

        if (!empty($filtros['campos'])) {
            $campos = BitrixHelper::formatarCampos(array_map('trim', explode(',', $filtros['campos'])));
            $filtrado = ['id' => $item['id'] ?? null];

            foreach ($campos as $campoConvertido) {
                if (isset($item[$campoConvertido])) {
                    $filtrado[$campoConvertido] = $item[$campoConvertido];
                }
            }

            return ['result' => ['item' => $filtrado]];
        }

        return $resultado;
    }
    
    // Baixa um arquivo associado a um negócio e retorna seu conteúdo em base64
    public static function baixarArquivoBase64($dados = []): ?array
{
    $spa = $dados['spa'] ?? null;
    $dealId = $dados['deal'] ?? null;
    $webhook = $dados['webhook'] ?? null;
    $campoArquivo = $dados['arquivoaserassinado'] ?? null;

    if (empty($spa) || empty($dealId) || empty($webhook) || empty($campoArquivo)) {
        return null;
    }

    // Converte o nome do campo do arquivo (ex: UF_CRM_...) para o padrão correto do Bitrix
    $campoArquivoFormatado = BitrixHelper::formatarCampos(['campo' => $campoArquivo])['campo'] ?? null;

    if (empty($campoArquivoFormatado)) {
        return null;
    }

    // Consulta os dados do negócio
    $negociacao = self::consultarNegociacao([
        'spa'     => $spa,
        'deal'    => $dealId,
        'webhook' => $webhook
    ]);

    if (empty($negociacao[$campoArquivoFormatado]) || !is_array($negociacao[$campoArquivoFormatado])) {
        return null;
    }

    // Pega o primeiro arquivo do campo (caso seja múltiplo)
    $arquivoInfo = reset($negociacao[$campoArquivoFormatado]);
    if (empty($arquivoInfo['url']) || empty($arquivoInfo['name'])) {
        return null;
    }

    // Baixa o conteúdo do arquivo via URL pública
    $conteudo = @file_get_contents($arquivoInfo['url']);
    if ($conteudo === false) {
        return null;
    }

    // Converte para base64
    $base64 = base64_encode($conteudo);
    // Extrai extensão do arquivo
    $extensao = pathinfo($arquivoInfo['name'], PATHINFO_EXTENSION);

    return [
        'base64'   => $base64,
        'nome'     => $arquivoInfo['name'],
        'extensao' => $extensao
    ];
}

}