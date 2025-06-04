<?php

class BitrixDiskHelper
{
    public static function obterArquivoPorId($webhook, $fileId)
    {
        $params = ["id" => $fileId];

        $resposta = BitrixHelper::chamarApi("disk.file.get", $params, [
            'webhook' => $webhook
        ]);

        return $resposta['result'] ?? null;
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
