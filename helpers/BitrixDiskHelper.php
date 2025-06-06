<?php

class BitrixDiskHelper
{
    /**
     * Ao invés de buscar novamente via API, retornamos direto o valor do campo do item
     * Isso evita erro de retorno vazio do disk.file.get
     */
    public static function extrairArquivoDoItem($item, $chaveCampo)
    {
        $valor = $item[$chaveCampo] ?? null;

        if (is_array($valor) && isset($valor[0]['urlMachine'])) {
            return $valor[0];
        }

        return null;
    }

    public static function obterLinkExterno($webhook, $fileId)
    {
        $params = ["id" => $fileId];

        $resposta = BitrixHelper::chamarApi("disk.file.getExternalLink", $params, [
            'webhook' => $webhook
        ]);

        return $resposta['result']['link'] ?? null;
    }

    /**
     * Função para detectar o MIME de um arquivo, caso a extensão não esteja presente
     */
    public static function obterMimeDoArquivo($url)
    {
        // Baixar o arquivo temporariamente para verificar o MIME
        $tmpPath = __DIR__ . "/arquivo_tmp_" . md5($url);
        file_put_contents($tmpPath, file_get_contents($url));

        // Detectar o MIME
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $tmpPath);
        finfo_close($finfo);

        // Apagar o arquivo temporário após verificar
        unlink($tmpPath);

        return $mime;
    }
}
