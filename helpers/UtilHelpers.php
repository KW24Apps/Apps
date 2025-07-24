<?php
namespace Helpers;

use DateTime;

class UtilHelpers
{
    // Baixa um arquivo de uma URL e retorna seus dados em base64
    public static function baixarArquivoBase64(array $arquivoInfo): ?array
    {
        if (empty($arquivoInfo['urlMachine'])) {
            return null;
        }

        $url = $arquivoInfo['urlMachine'];
        $nome = $arquivoInfo['name'] ?? self::extrairNomeArquivoDaUrl($url);

        if (empty($nome)) {
            return null;
        }

        $conteudo = file_get_contents($url);

        if ($conteudo === false) {
            $erro = error_get_last(); // Captura o warning real do PHP
            return null;
        }
        $hashArquivo = md5($conteudo);
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

    // Normaliza valores monetários para float
    public static function normalizarValor($entrada)
    {
        $valor = trim($entrada);

        if (preg_match('/^\d+\.\d{1,2}$/', $valor)) {
            return floatval($valor);
        }

        if (preg_match('/^\d+,\d{1,2}$/', $valor)) {
            return floatval(str_replace(',', '.', $valor));
        }

        if (strpos($valor, '.') !== false && strpos($valor, ',') !== false) {
            $valor = str_replace('.', '', $valor);
            $valor = str_replace(',', '.', $valor);
            return floatval($valor);
        }

        return floatval(str_replace(',', '.', preg_replace('/[^\d.,]/', '', $valor)));
    }

    // Formata um valor monetário por extenso
    public static function valorPorExtenso($valor)
    {
        $fmt = new \NumberFormatter('pt_BR', \NumberFormatter::SPELLOUT);
        $inteiro = floor($valor);
        $centavos = round(($valor - $inteiro) * 100);

        $texto = $fmt->format($inteiro) . ' reais';
        if ($centavos > 0) {
            $texto .= ' e ' . $fmt->format($centavos) . ' centavos';
        }

        return $texto;
    }

    // Formata Compos do Bitrix
    public static function formatarCampos($dados)
    {
        $fields = [];

        foreach ($dados as $campo => $valor) {
            // Normaliza prefixos quebrados como ufcrm_ ou uf_crm_
            $campoNormalizado = strtoupper(str_replace(['ufcrm_', 'uf_crm_'], 'UF_CRM_', $campo));

            if (strpos($campoNormalizado, 'UF_CRM_') === 0) {
                $chaveConvertida = 'ufCrm' . substr($campoNormalizado, 7);
                $fields[$chaveConvertida] = $valor;
            } else {
                $fields[$campo] = $valor;
            }
        }

        return $fields;
    }

    // Calcula uma data útil adicionando X dias úteis
    public static function calcularDataUtil(int $dias): DateTime
    {
        $data = new DateTime();
        $adicionados = 0;

        while ($adicionados < $dias) {
            $data->modify('+1 day');
            $diaSemana = $data->format('N');
            if ($diaSemana < 6) {
                $adicionados++;
            }
        }
        return $data;
    }

    // Detecta a aplicação com base na URI
    public static function detectarAplicacaoPorUri($uri)
    {
        $uri = ltrim($uri, '/');
        $slug = null;
        if (strpos($uri, 'geraroportunidades') === 0) {
            $slug = 'geraroptnd';
        } elseif (strpos($uri, 'scheduler') === 0) {
            $slug = 'scheduler';
        } elseif (strpos($uri, 'deal') === 0) {
            $slug = 'deal';
        } elseif (strpos($uri, 'extenso') === 0) {
            $slug = 'extenso';
        } elseif (strpos($uri, 'clicksign') === 0) {
            $slug = 'clicksign';
        } elseif (strpos($uri, 'company') === 0) {
            $slug = 'company';
        } elseif (strpos($uri, 'mediahora') === 0) {
            $slug = 'mediahora';
        } elseif (strpos($uri, 'omie') === 0) {
            $slug = 'omie';
        } elseif (strpos($uri, 'bitrix-sync') === 0) {
            $slug = 'bitrix-sync';
        }
        if (!defined('NOME_APLICACAO')) {
            define('NOME_APLICACAO', $slug ?: 'desconhecida');
        }
        return $slug ?: 'desconhecida';
    }

}