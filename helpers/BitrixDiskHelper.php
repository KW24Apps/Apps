<?php
require_once __DIR__ . '/../helpers/BitrixHelper.php';
require_once __DIR__ . '/../helpers/LogHelper.php';

class BitrixDiskHelper
{
    /**
     * Ao invés de buscar novamente via API, retornamos direto o valor do campo do item
     * Isso evita erro de retorno vazio do disk.file.get
     */
    public static function extrairArquivoDoItem($item, $chaveCampo)
    {
        // Caminho do log específico para o BitrixDiskHelper
        $logFile = 'X:/VSCode/apis.kw24.com.br/Apps/logs/bitrix_disk_helper.log';

        // Acesso ao campo de arquivo
        $valor = $item[$chaveCampo] ?? null;

        // Log para verificar se o valor do campo foi encontrado
        error_log("Campo do arquivo (chave: $chaveCampo): " . print_r($valor, true) . PHP_EOL, 3, $logFile);

        // Verificar se o campo de arquivo é um array e acessar a primeira URL válida
        if (is_array($valor)) {
            foreach ($valor as $arquivo) {
                // Verificar se a urlMachine está presente
                if (isset($arquivo['urlMachine'])) {
                    // Log para verificar a URL do arquivo
                    error_log("URL do arquivo (urlMachine): " . print_r($arquivo['urlMachine'], true) . PHP_EOL, 3, $logFile);
                    return $arquivo;
                }
            }
        }

        // Se não encontrar o arquivo ou urlMachine, registrar que falhou
        error_log("Arquivo não encontrado ou URL inválida." . PHP_EOL, 3, $logFile);

        return null;
    }


    public static function obterLinkExterno($webhook, $fileId)
    {
        // Caminho do log específico para o BitrixDiskHelper
        $logFile = 'X:/VSCode/apis.kw24.com.br/Apps/logs/bitrix_disk_helper.log';

        $params = ["id" => $fileId];

        // Log para registrar a chamada à API para obter link externo
        error_log("Consultando link externo para o arquivo ID: $fileId" . PHP_EOL, 3, $logFile);

        $resposta = BitrixHelper::chamarApi("disk.file.getExternalLink", $params, [
            'webhook' => $webhook
        ]);

        // Log para registrar a resposta da API
        error_log("Resposta da API disk.file.getExternalLink: " . print_r($resposta, true) . PHP_EOL, 3, $logFile);

        return $resposta['result']['link'] ?? null;
    }

    /**
     * Função para detectar o MIME de um arquivo, caso a extensão não esteja presente
     */
    public static function obterMimeDoArquivo($url)
    {
        // Caminho do log específico para o BitrixDiskHelper
        $logFile = 'X:/VSCode/apis.kw24.com.br/Apps/logs/bitrix_disk_helper.log';

        // Log para registrar o início da detecção do MIME
        error_log("Detectando MIME para o arquivo: $url" . PHP_EOL, 3, $logFile);

        // Baixar o arquivo temporariamente para verificar o MIME
        $tmpPath = __DIR__ . "/arquivo_tmp_" . md5($url);
        file_put_contents($tmpPath, file_get_contents($url));

        // Detectar o MIME
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $tmpPath);
        finfo_close($finfo);

        // Log para registrar o tipo MIME detectado
        error_log("Tipo MIME detectado: " . $mime . PHP_EOL, 3, $logFile);

        // Apagar o arquivo temporário após verificar
        unlink($tmpPath);

        return $mime;
    }
}
