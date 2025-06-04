<?php

class BitrixDiskHelper
{
    public static function obterArquivoPorId($webhook, $fileId)
    {
        $url = $webhook . "disk.file.get";
        $params = ["id" => $fileId];

        $resposta = BitrixHelper::chamarApi($url, $params);

        return $resposta['result'] ?? null;
    }

    public static function obterLinkExterno($webhook, $fileId)
    {
        $url = $webhook . "disk.file.getExternalLink";
        $params = ["id" => $fileId];

        $resposta = BitrixHelper::chamarApi($url, $params);

        return $resposta['result']['link'] ?? null;
    }
}
