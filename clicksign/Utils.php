<?php

class Utils
{
    public static function downloadFileDetails($diretorioIgnorado, $url)
    {
        $pastaLocal = __DIR__ . '/tmp/';
        if (!file_exists($pastaLocal)) {
            mkdir($pastaLocal, 0777, true);
        }

        $logPath = __DIR__ . '/log_curl.txt';

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        $data = curl_exec($ch);
        $info = curl_getinfo($ch);
        $erro = curl_error($ch);

        if ($erro) {
            file_put_contents($logPath, "[" . date('Y-m-d H:i:s') . "] Erro cURL: $erro\nURL: $url\n", FILE_APPEND);
        } else {
            file_put_contents($logPath, "[" . date('Y-m-d H:i:s') . "] Download OK. Status: {$info['http_code']}\n", FILE_APPEND);
        }

        curl_close($ch);

        $filename = 'arquivo_' . time() . '.pdf';
        file_put_contents($pastaLocal . $filename, $data);

        return [
            'filename' => $filename,
            'contentType' => $info['content_type'] ?? 'application/pdf'
        ];
    }
}
