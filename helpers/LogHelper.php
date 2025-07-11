<?php
class LogHelper
{
    public static function logClickSign(string $mensagem, string $contexto = ''): void
    {
        $arquivoLog = __DIR__ . '/../logs/clicksign.log';
        $timestamp = date('Y-m-d H:i:s');
        $linha = "[$timestamp]" . ($contexto ? " [$contexto]" : "") . " - $mensagem" . PHP_EOL;
        file_put_contents($arquivoLog, $linha, FILE_APPEND);
    }

        public static function logBitrixHelpers(string $mensagem, string $contexto = ''): void
    {
        $arquivoLog = __DIR__ . '/../logs/BitrixHelpers.log';
        $timestamp = date('Y-m-d H:i:s');
        $linha = "[$timestamp]" . ($contexto ? " [$contexto]" : "") . " - $mensagem" . PHP_EOL;
        file_put_contents($arquivoLog, $linha, FILE_APPEND);
    }

        public static function logBitrixDealHelpers(string $mensagem, string $contexto = ''): void
    {
        $arquivoLog = __DIR__ . '/../logs/BitrixDealHelpers.log';
        $timestamp = date('Y-m-d H:i:s');
        $linha = "[$timestamp]" . ($contexto ? " [$contexto]" : "") . " - $mensagem" . PHP_EOL;
        file_put_contents($arquivoLog, $linha, FILE_APPEND);
    } 

        public static function logRotas(string $uri, string $method, string $contexto = '', string $mensagemExtra = '')
    {
        $arquivoLog = __DIR__ . '/../logs/rotas.log';
        $timestamp = date('Y-m-d H:i:s');
        $linha = "[$timestamp]" . ($contexto ? " [$contexto]" : "") . " - URI: $uri | MÉTODO: $method" . PHP_EOL;
        file_put_contents($arquivoLog, $linha, FILE_APPEND);
    }
} 