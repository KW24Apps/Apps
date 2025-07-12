<?php
class LogHelper
{
    // Registra uma entrada global no log
        public static function registrarEntradaGlobal(string $uri, string $method, string $destino = ''): void
    {
        $arquivoLog = __DIR__ . '/../logs/logEntradasGlobais.log';
        $timestamp = date('Y-m-d H:i:s');
        $linha = "[$timestamp] - URI: $uri | MÉTODO: $method" . ($destino ? " | DESTINO: $destino" : "") . PHP_EOL;
        file_put_contents($arquivoLog, $linha, FILE_APPEND);
    }

    // Registra um erro global no log
    public static function registrarErroGlobal($errno = null, $errstr = '', $errfile = '', $errline = ''): void
    {
        $arquivoLog = __DIR__ . '/../logs/erros_global.log';
        $timestamp = date('Y-m-d H:i:s');

        $mensagem = "[Erro]";
        if ($errno !== null) {
            $mensagem .= " [$errno] $errstr em $errfile na linha $errline";
        } else {
            $mensagem .= " Erro não identificado";
        }

        $linha = "[$timestamp] - $mensagem" . PHP_EOL;
        file_put_contents($arquivoLog, $linha, FILE_APPEND);
    }


    // Registra uma rota não encontrada no log
    public static function registrarRotaNaoEncontrada(string $uri, string $method, string $arquivoRota): void
    {
        $arquivoLog = __DIR__ . '/../logs/logRotasNaoEncontradas.log';
        $timestamp = date('Y-m-d H:i:s');
        $linha = "[$timestamp] [$arquivoRota] - Rota não encontrada | URI: $uri | MÉTODO: $method" . PHP_EOL;
        file_put_contents($arquivoLog, $linha, FILE_APPEND);
    }

    // Registra uma mensagem de log para ClickSign
    public static function logClickSign(string $mensagem, string $contexto = ''): void
    {
        $arquivoLog = __DIR__ . '/../logs/clicksign.log';
        $timestamp = date('Y-m-d H:i:s');
        $linha = "[$timestamp]" . ($contexto ? " [$contexto]" : "") . " - $mensagem" . PHP_EOL;
        file_put_contents($arquivoLog, $linha, FILE_APPEND);
    }

    // Registra uma mensagem de log para BitrixHelpers
        public static function logBitrixHelpers(string $mensagem, string $contexto = ''): void
    {
        $arquivoLog = __DIR__ . '/../logs/BitrixHelpers.log';
        $timestamp = date('Y-m-d H:i:s');
        $linha = "[$timestamp]" . ($contexto ? " [$contexto]" : "") . " - $mensagem" . PHP_EOL;
        file_put_contents($arquivoLog, $linha, FILE_APPEND);
    }

    // Registra uma mensagem de log para BitrixDealHelpers
        public static function logBitrixDealHelpers(string $mensagem, string $contexto = ''): void
    {
        $arquivoLog = __DIR__ . '/../logs/BitrixDealHelpers.log';
        $timestamp = date('Y-m-d H:i:s');
        $linha = "[$timestamp]" . ($contexto ? " [$contexto]" : "") . " - $mensagem" . PHP_EOL;
        file_put_contents($arquivoLog, $linha, FILE_APPEND);
    } 

    // Registra uma mensagem de log para documentos assinados
    public static function logDocumentoAssinado(string $mensagem, string $contexto = '')
    {
        $arquivoLog = __DIR__ . '/../logs/documentoassinado.log';
        $timestamp = date('Y-m-d H:i:s');
        $linha = "[$timestamp]" . ($contexto ? " [$contexto]" : "") . " - $mensagem" . PHP_EOL;
        file_put_contents($arquivoLog, $linha, FILE_APPEND);
    }

}