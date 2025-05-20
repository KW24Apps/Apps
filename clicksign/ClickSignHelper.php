<?php

class ClickSignHelper
{
    private $token = '0d388bbc-640e-4f99-9d67-77513b41f36d';

    public function criarDocumento($base64, $filename, $deadline, $mime)
    {
        $url = "https://app.clicksign.com/api/v1/documents?access_token=" . $this->token;

        $payload = [
            "path" => $filename,
            "content_base64" => $base64,
            "mime_type" => $mime,
            "deadline_at" => $deadline,
            "auto_close" => true
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($payload)
        ]);

        $resposta = curl_exec($ch);
        $erro = curl_error($ch);
        curl_close($ch);

        if ($erro) {
            return (object)[
                "errors" => ["Erro cURL: $erro"]
            ];
        }

        return json_decode($resposta);
    }
}
