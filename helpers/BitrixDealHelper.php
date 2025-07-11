<?php
require_once __DIR__ . '/../helpers/BitrixHelper.php';
require_once __DIR__ . '/../helpers/LogHelper.php';
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
public static function consultarDeal($entityId, $id, $fields, $webhook)
{
    // Normaliza campos para array e remove espaços
    if (is_string($fields)) {
        $fields = array_map('trim', explode(',', $fields));
    } else {
        $fields = array_map('trim', $fields);
    }

    if (!in_array('id', $fields)) {
        array_unshift($fields, 'id');
    }
 
    $params = [
        'entityTypeId' => $entityId,
        'id' => $id,
    ];

    $respostaApi = BitrixHelper::chamarApi('crm.item.get', $params, [
        'webhook' => $webhook
    ]);

    $dadosBrutos = $respostaApi['result']['item'] ?? [];

    $camposFormatados = BitrixHelper::formatarCampos(array_fill_keys($fields, null));
    $resultadoFinal = [];

    foreach (array_keys($camposFormatados) as $campoConvertido) {
        $resultadoFinal[$campoConvertido] = $dadosBrutos[$campoConvertido] ?? null;
    }
    
    return ['result' => ['item' => $resultadoFinal]];
    }

    // Baixa um arquivo de uma URL e retorna seus dados em base64
    public static function baixarArquivoBase64(array $arquivoInfo): ?array
    {
        if (empty($arquivoInfo['urlMachine'])) {
            LogHelper::logClickSign("URL do arquivo não informada.", 'baixarArquivoBase64ComDados');
            return null;
        }

        $url = $arquivoInfo['urlMachine'];
        $nome = $arquivoInfo['name'] ?? self::extrairNomeArquivoDaUrl($url);

        if (empty($nome)) {
            LogHelper::logClickSign("Nome do arquivo não identificado.", 'baixarArquivoBase64ComDados');
            return null;
        }

        $conteudo = file_get_contents($url);

        if ($conteudo === false) {
            $erro = error_get_last(); // Captura o warning real do PHP
            LogHelper::logClickSign(
                "Falha ao baixar o arquivo da URL: {$url} | Erro: " . json_encode($erro),
                'baixarArquivoBase64ComDados'
            );
            return null;
        }

        $base64 = base64_encode($conteudo);
        $mime = ClickSignHelper::obterMimeDoArquivo($url);
        $extensao = ClickSignHelper::mimeParaExtensao($mime) ?? pathinfo($nome, PATHINFO_EXTENSION);

        if (strtolower(pathinfo($nome, PATHINFO_EXTENSION)) !== strtolower($extensao)) {
            $nome .= '.' . $extensao;
        }


        return [
            'base64'   => 'data:' . $mime . ';base64,' . $base64,
            'nome'     => $nome,
            'extensao' => $extensao,
            'mime'     => $mime
        ];
    }


    // Extrai o nome do arquivo de uma URL com parâmetros
    public static function extrairNomeArquivoDaUrl(string $url, string $dir = '/tmp/'): string
    {
        // Baixa o arquivo e tenta pegar o nome real do header Content-Disposition
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 1);

        if (!file_exists($dir)) {
            mkdir($dir, 0777, true);
        }

        $response = curl_exec($ch);
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $header = substr($response, 0, $header_size);
        $body = substr($response, $header_size);

        curl_close($ch);

        preg_match('/content-disposition:.*filename=["\']?([^"\';]+)["\';]?/i', $header, $filenameMatches);
        $filename = isset($filenameMatches[1]) ? trim($filenameMatches[1]) : '';

        // Se não achou nome no header, mantém a lógica antiga de extrair pela URL
        if (!$filename) {
            $extensoesPermitidas = ['pdf', 'png', 'jpg', 'jpeg', 'doc', 'docx'];
            $nomeArquivo = null;

            if (!empty($url) && preg_match('/fileName=([^&]+)/', $url, $match)) {
                $nomeArquivo = urldecode($match[1]);
            }

            if (empty($nomeArquivo)) {
                $path = parse_url($url, PHP_URL_PATH);
                $ext = pathinfo($path, PATHINFO_EXTENSION);

                if (in_array(strtolower($ext), $extensoesPermitidas)) {
                    $filename = 'arquivo_desconhecido.' . $ext;
                } else {
                    $filename = 'arquivo_desconhecido.docx';
                }
            } else {
                $ext = pathinfo($nomeArquivo, PATHINFO_EXTENSION);
                if (in_array(strtolower($ext), $extensoesPermitidas)) {
                    $filename = $nomeArquivo;
                } else {
                    $base = pathinfo($nomeArquivo, PATHINFO_FILENAME);
                    $filename = $base . '.docx';
                }
            }
        }

        // Salva o arquivo, se quiser (opcional)
        // file_put_contents($dir . $filename, $body);

        return $filename;
    }

    // Anexa um arquivo a um negócio no Bitrix24
    public static function anexarArquivoNegocio($spa, $dealId, $campoArquivo, $urlArquivo, $nomeArquivo = null)
    {
        // Usa a função já existente para baixar e preparar o arquivo em base64
        $arquivoInfo = [
            'urlMachine' => $urlArquivo,
            'name' => $nomeArquivo
        ];
        $arquivoBase64 = self::baixarArquivoBase64($arquivoInfo);

        if (!$arquivoBase64) {
            LogHelper::logClickSign("Erro ao baixar/converter arquivo para anexo no negócio", 'BitrixDealHelper');
            return false;
        }

        // Monta o array conforme Bitrix espera
        $arquivoParaBitrix = [
            [
                'filename' => $arquivoBase64['nome'],
                'data'     => str_replace('data:' . $arquivoBase64['mime'] . ';base64,', '', $arquivoBase64['base64']) // Só o base64 puro!
            ]
        ];

        // Monta e dispara o update usando BitrixHelper
        $params = [
            'entityTypeId' => $spa,
            'id'           => $dealId,
            'fields'       => [
                $campoArquivo => $arquivoParaBitrix
            ]
        ];

        $resultado = BitrixHelper::chamarApi('crm.item.update', $params, [
            'log' => true // ou 'webhook' => $webhook se quiser personalizar
        ]);

        return $resultado;
    }



}