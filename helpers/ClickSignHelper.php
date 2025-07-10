<?php

require_once __DIR__ . '/../helpers/LogHelper.php';

class ClickSignHelper
{
    private static function enviarRequisicao($metodo, $endpoint, $token, $dados = [])
    {
        if (!is_string($endpoint)) {
            LogHelper::logClickSign('[ERRO] Endpoint não é string: ' . json_encode($endpoint), 'ClickSignHelper');
            return null;
        }

        $url = 'https://app.clicksign.com/api/v1' . $endpoint . '?access_token=' . $token;

        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $metodo,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($dados),
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        ]);

        $resposta = curl_exec($ch);

        if ($resposta === false) {
            $curlError = curl_error($ch);
            LogHelper::logClickSign('[ClickSignHelper] - Erro cURL: ' . $curlError, 'ClickSignHelper');
        }

        file_put_contents(__DIR__ . '/../logs/clicksign_debug.log', "[RESPOSTA] " . $resposta . PHP_EOL, FILE_APPEND);
        curl_close($ch);

        return json_decode($resposta, true);
    }

    // DOCUMENTO — Criação
    public static function criarDocumento($payload, $token)
    {
        return self::enviarRequisicao('POST', '/documents', $token, $payload);
    }

    // DOCUMENTO — Consulta (útil para debug de erros de vínculo)
    public static function buscarDocumento($token, $documentKey)
    {
        return self::enviarRequisicao('GET', "/documents/$documentKey", $token);
    }

    // SIGNATÁRIO — Criação (utilizando V2 — endpoint externo, não pelo helper genérico)
    public static function criarSignatario($token, $dados)
    {
        // Atenção: endpoint V2 direto!
        $url = 'https://app.clicksign.com/api/v2/signers?access_token=' . $token;
        $ch = curl_init($url);
        $signerPayload = [
            "signer" => [
                "name" => $dados["name"],
                "email" => $dados["email"],
                "auths" => $dados["auths"] ?? ["email"]
            ]
        ];
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($signerPayload),
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        ]);
        $resposta = curl_exec($ch);
        if ($resposta === false) {
            $curlError = curl_error($ch);
            LogHelper::logClickSign('[ClickSignHelper][criarSignatario] - Erro cURL: ' . $curlError, 'ClickSignHelper');
        }
        file_put_contents(__DIR__ . '/../logs/clicksign_debug.log', "[RESPOSTA] " . $resposta . PHP_EOL, FILE_APPEND);
        curl_close($ch);
        return json_decode($resposta, true);
    }

    // SIGNATÁRIO — Vínculo ao documento (sempre V1/lists)
    public static function vincularSignatario($token, $dados)
    {
        // Aqui, 'list' já deve ser o array padrão esperado pela API
        return self::enviarRequisicao('POST', '/lists', $token, ['list' => $dados]);
    }

    public static function obterMimeDoArquivo(string $url): ?string
    {
        $headers = get_headers($url, 1);
        return $headers['Content-Type'] ?? null;
    }

    public static function mimeParaExtensao(string $mime): ?string
    {
        $map = [
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            // adiciona outros conforme necessidade
        ];

        return $map[strtolower($mime)] ?? null;
    }

    public static function extensaoParaMime(string $extensao): ?string
    {
        $map = [
            'pdf' => 'application/pdf',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            // adiciona outros conforme necessidade
        ];

        return $map[strtolower($extensao)] ?? null;
    }


}
