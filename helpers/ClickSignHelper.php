<?php

require_once __DIR__ . '/../helpers/LogHelper.php';

class ClickSignHelper
{
    // Método genérico para enviar requisições à API ClickSign
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

    // Envia notificação ao signatário (com lembrete automático de 2 em 2 dias)
    public static function enviarNotificacao($token, $listKey, $reminderInterval = 2)
    {
        $payload = [
            'notification' => [
                'list_key' => $listKey,
                'reminder_interval' => $reminderInterval,
                'delivery' => 'email'
            ]
        ];
        return self::enviarRequisicao('POST', '/notifications', $token, $payload);
    }

    // DOCUMENTO — Assinatura (sempre V1/signatures)
    public static function obterMimeDoArquivo(string $url): ?string
    {
        $headers = get_headers($url, 1);
        return $headers['Content-Type'] ?? null;
    }

    // DOCUMENTO — Assinatura (sempre V1/signatures)
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

    // DOCUMENTO — Assinatura (sempre V1/signatures)
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

    // Validação HMAC
        public static function validarHmac($body, $secret, $headerSignature)
    {
        if (!$headerSignature) {
            LogHelper::logClickSign("Header HMAC não recebido", 'controller');
            return false;
        }

        if (strpos($headerSignature, 'sha256=') === 0) {
            $receivedSignature = substr($headerSignature, strlen('sha256='));
        } else {
            LogHelper::logClickSign("Header HMAC inválido: $headerSignature", 'controller');
            return false;
        }

        $calculatedSignature = hash_hmac('sha256', $body, $secret);

        if ($receivedSignature !== $calculatedSignature) {
            LogHelper::logClickSign("Assinatura HMAC inválida | Recebida: $receivedSignature | Calculada: $calculatedSignature", 'controller');
            return false;
        }

        return true;
    }


}


