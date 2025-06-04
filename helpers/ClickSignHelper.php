<?php

class ClickSignHelper
{
     private static function enviarRequisicao($metodo, $endpoint, $token, $dados = null)
    {
        $url = 'https://app.clicksign.com/api/v1' . $endpoint . '?access_token=' . $token;
        $headers = [
            'Content-Type: application/json'
        ];

        $logPath = __DIR__ . '/../logs/clicksign_envio.log';
        $logDados = [
            'url' => $url,
            'metodo' => $metodo,
            'headers' => $headers,
            'dados' => $dados
        ];
        file_put_contents($logPath, "[ENVIANDO PARA CLICKSIGN] " . json_encode($logDados) . PHP_EOL, FILE_APPEND);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $metodo);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if ($dados !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($dados));
        }

        $resposta = curl_exec($ch);
        $erro = curl_error($ch);
        curl_close($ch);

        return $erro ? ['erro' => $erro] : json_decode($resposta, true);
    }

    // Documentos
    public static function criarDocumento($token, $nome, $urlArquivo)
    {
        $conteudo = @file_get_contents($urlArquivo);

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_buffer($finfo, $conteudo);
        finfo_close($finfo);

        $base64 = base64_encode($conteudo);
        $path = '/' . basename(parse_url($urlArquivo, PHP_URL_PATH));

        return self::enviarRequisicao('POST', '/documents', $token, [
            'document' => [
                'path' => $path,
                'content_base64' => $base64,
                'name' => $nome
            ]
        ]);
    }

    public static function buscarDocumento($token, $documentKey)
    {
        return self::enviarRequisicao('GET', "/documents/$documentKey", $token);
    }

    public static function cancelarDocumento($token, $documentKey)
    {
        return self::enviarRequisicao('PATCH', "/documents/$documentKey/cancel", $token);
    }

    public static function fecharDocumento($token, $documentKey)
    {
        return self::enviarRequisicao('PATCH', "/documents/$documentKey/close", $token);
    }

    public static function baixarDocumentoAssinado($token, $documentKey)
    {
        return self::enviarRequisicao('GET', "/documents/$documentKey/download", $token);
    }

    // Signatários
    public static function criarSignatario($token, $dados)
    {
        return self::enviarRequisicao('POST', '/signers', $token, ['signer' => $dados]);
    }

    public static function atualizarSignatario($token, $signerKey, $dados)
    {
        return self::enviarRequisicao('PATCH', "/signers/$signerKey", $token, ['signer' => $dados]);
    }

    public static function listarSignatarios($token)
    {
        return self::enviarRequisicao('GET', '/signers', $token);
    }

    // Associação de signatário
    public static function vincularSignatario($token, $dados)
    {
        return self::enviarRequisicao('POST', '/lists', $token, ['list' => $dados]);
    }

    public static function removerSignatario($token, $listKey)
    {
        return self::enviarRequisicao('DELETE', "/lists/$listKey", $token);
    }

    // Envelopes
    public static function criarEnvelope($token, $dados)
    {
        return self::enviarRequisicao('POST', '/envelopes', $token, ['envelope' => $dados]);
    }

    public static function adicionarDocumentoAoEnvelope($token, $envelopeKey, $documentKey)
    {
        return self::enviarRequisicao('POST', "/envelopes/$envelopeKey/add_documents", $token, [
            'document_key' => $documentKey
        ]);
    }

    public static function adicionarSignatarioAoEnvelope($token, $envelopeKey, $signerKey)
    {
        return self::enviarRequisicao('POST', "/envelopes/$envelopeKey/add_signers", $token, [
            'signer_key' => $signerKey
        ]);
    }

    public static function ativarEnvelope($token, $envelopeKey)
    {
        return self::enviarRequisicao('PATCH', "/envelopes/$envelopeKey/activate", $token);
    }

    public static function listarEnvelopes($token)
    {
        return self::enviarRequisicao('GET', '/envelopes', $token);
    }

    // Notificações
    public static function enviarNotificacao($token, $listKey)
    {
        return self::enviarRequisicao('POST', '/notifications', $token, ['request_signature_key' => $listKey]);
    }

    // Webhooks
    public static function criarWebhook($token, $dados)
    {
        return self::enviarRequisicao('POST', '/webhooks', $token, ['webhook' => $dados]);
    }

    public static function listarWebhooks($token)
    {
        return self::enviarRequisicao('GET', '/webhooks', $token);
    }

    public static function removerWebhook($token, $webhookKey)
    {
        return self::enviarRequisicao('DELETE', "/webhooks/$webhookKey", $token);
    }

    // WhatsApp
    public static function criarAceiteWhatsApp($token, $dados)
    {
        return self::enviarRequisicao('POST', '/accepts/whatsapp', $token, ['accept' => $dados]);
    }

    public static function visualizarAceiteWhatsApp($token, $key)
    {
        return self::enviarRequisicao('GET', "/accepts/whatsapp/$key", $token);
    }

    public static function listarAceitesWhatsApp($token)
    {
        return self::enviarRequisicao('GET', '/accepts/whatsapp', $token);
    }

    public static function cancelarAceiteWhatsApp($token, $key)
    {
        return self::enviarRequisicao('PATCH', "/accepts/whatsapp/$key/cancel", $token);
    }
}
