<?php

class UtilHelpers
{
    // Baixa um arquivo de uma URL e retorna seus dados em base64
    public static function baixarArquivoBase64(array $arquivoInfo): ?array
    {
        if (empty($arquivoInfo['urlMachine'])) {
            LogHelper::logClickSign("URL do arquivo não informada.", 'baixarArquivoBase64ComDados');
            return null;
        }

        $url = $arquivoInfo['urlMachine'];
        $nome = $arquivoInfo['name'] ?? self::extrairNomeArquivoDaUrl($url);

        if (empty($nome)) {
            LogHelper::logClickSign("Nome do arquivo não identificado.", 'baixarArquivoBase64ComDados');
            return null;
        }

        $conteudo = file_get_contents($url);

        if ($conteudo === false) {
            $erro = error_get_last(); // Captura o warning real do PHP
            LogHelper::logClickSign("Falha ao baixar o arquivo da URL: {$url} | Erro: " . json_encode($erro),'baixarArquivoBase64ComDados');
            return null;
        }
        $hashArquivo = md5($conteudo);
        LogHelper::logDocumentoAssinado("Hash do conteúdo baixado: $hashArquivo | URL: $url", 'baixarArquivoBase64');

        $base64 = base64_encode($conteudo);
        $mime = ClickSignHelper::obterMimeDoArquivo($url);
        $extensao = ClickSignHelper::mimeParaExtensao($mime) ?? pathinfo($nome, PATHINFO_EXTENSION);

        if (strtolower(pathinfo($nome, PATHINFO_EXTENSION)) !== strtolower($extensao)) {
            $nome .= '.' . $extensao;
        }


        return [
            'base64'   => 'data:' . $mime . ';base64,' . $base64,
            'nome'     => $nome,
            'extensao' => $extensao,
            'mime'     => $mime
        ];
    }

    // Extrai o nome do arquivo de uma URL com parâmetros
    public static function extrairNomeArquivoDaUrl(string $url, string $dir = '/tmp/'): string
    {
        // Baixa o arquivo e tenta pegar o nome real do header Content-Disposition
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 1);

        if (!file_exists($dir)) {
            mkdir($dir, 0777, true);
        }

        $response = curl_exec($ch);
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $header = substr($response, 0, $header_size);
        $body = substr($response, $header_size);

        curl_close($ch);

        preg_match('/content-disposition:.*filename=["\']?([^"\';]+)["\';]?/i', $header, $filenameMatches);
        $filename = isset($filenameMatches[1]) ? trim($filenameMatches[1]) : '';

        // Se não achou nome no header, mantém a lógica antiga de extrair pela URL
        if (!$filename) {
            $extensoesPermitidas = ['pdf', 'png', 'jpg', 'jpeg', 'doc', 'docx'];
            $nomeArquivo = null;

            if (!empty($url) && preg_match('/fileName=([^&]+)/', $url, $match)) {
                $nomeArquivo = urldecode($match[1]);
            }

            if (empty($nomeArquivo)) {
                $path = parse_url($url, PHP_URL_PATH);
                $ext = pathinfo($path, PATHINFO_EXTENSION);

                if (in_array(strtolower($ext), $extensoesPermitidas)) {
                    $filename = 'arquivo_desconhecido.' . $ext;
                } else {
                    $filename = 'arquivo_desconhecido.docx';
                }
            } else {
                $ext = pathinfo($nomeArquivo, PATHINFO_EXTENSION);
                if (in_array(strtolower($ext), $extensoesPermitidas)) {
                    $filename = $nomeArquivo;
                } else {
                    $base = pathinfo($nomeArquivo, PATHINFO_FILENAME);
                    $filename = $base . '.docx';
                }
            }
        }

        // Salva o arquivo, se quiser (opcional)
        // file_put_contents($dir . $filename, $body);

        return $filename;
    }

}