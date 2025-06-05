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
}
