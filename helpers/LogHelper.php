<?php
namespace Helpers;

class LogHelper
{

    // Gera um trace ID único para cada requisição
    public static function gerarTraceId(): void
    {
        if (!defined('TRACE_ID')) {
            $traceId = bin2hex(random_bytes(4));
            define('TRACE_ID', $traceId);
        }
    }

    // Registra uma entrada global no log
    public static function registrarEntradaGlobal(string $uri, string $method): void
    {
        $evento = null;
        $aplicacao = defined('NOME_APLICACAO') ? NOME_APLICACAO : 'desconhecida';
        $contexto = 'Index::EntradaGlobal';

        if ($uri === 'clicksignretorno' && $method === 'POST') {
            $body = file_get_contents('php://input');
            $json = json_decode($body, true);
            $evento = $json['event']['name'] ?? null;
        }

        $arquivoLog = __DIR__ . '/../logs/entradas.log';
        $timestamp = date('Y-m-d H:i:s');
        $traceId = defined('TRACE_ID') ? TRACE_ID : 'sem_trace';
         $linha = "[$timestamp] [$traceId] [$aplicacao] [$contexto] - URI: $uri | MÉTODO: $method";
        if ($evento) $linha .= " | EVENTO: $evento";
        $linha .= PHP_EOL;
        file_put_contents($arquivoLog, $linha, FILE_APPEND);
    }

    // Registra um erro global no log
    public static function registrarErroGlobal($errno = null, $errstr = '', $errfile = '', $errline = ''): void
    {
        $arquivoLog = __DIR__ . '/../logs/erros_global.log';
        $timestamp = date('Y-m-d H:i:s');
        $traceId = defined('TRACE_ID') ? TRACE_ID : 'sem_trace';
        $aplicacao = defined('NOME_APLICACAO') ? NOME_APLICACAO : 'desconhecida';
        $contexto = 'Index::ErroGlobal';
        $mensagem = "[Erro]";
        if ($errno !== null) {
            $mensagem .= " [$errno] $errstr em $errfile na linha $errline";
        } else {
            $mensagem .= " Erro não identificado";
        }

         $linha = "[$timestamp] [$traceId] [$aplicacao] [$contexto] - $mensagem" . PHP_EOL;
        file_put_contents($arquivoLog, $linha, FILE_APPEND);
    }

    // Registra uma rota não encontrada no log
    public static function registrarRotaNaoEncontrada(string $uri, string $method, string $arquivoRota): void
    {
        $arquivoLog = __DIR__ . '/../../../logs/logRotasNaoEncontradas.log';
        $timestamp = date('Y-m-d H:i:s');
        $traceId = defined('TRACE_ID') ? TRACE_ID : 'sem_trace';
        $aplicacao = defined('NOME_APLICACAO') ? NOME_APLICACAO : 'desconhecida';
        $contexto = 'Index::RotaNaoEncontrada';

        $linha = "[$timestamp] [$traceId] [$aplicacao] [$contexto] - $arquivoRota | Rota não encontrada | URI: $uri | MÉTODO: $method" . PHP_EOL;
        file_put_contents($arquivoLog, $linha, FILE_APPEND);
    }

    // Registra de Log de Permição de Cliente/Apilicação
    public static function logAcessoAplicacao(array $dados, string $contexto): void
    {
        $arquivoLog = __DIR__ . '/../../../logs/aplicacao_acesso.log';
        $timestamp = date('Y-m-d H:i:s');
        $traceId = defined('TRACE_ID') ? TRACE_ID : 'sem_trace';
        $aplicacao = defined('NOME_APLICACAO') ? NOME_APLICACAO : 'desconhecida';

        // Se não passar contexto, tenta pegar automaticamente
        if (!$contexto) {
            $bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
            $contexto = $bt[1]['function'] ?? 'desconhecido';
        }

        $linha = "[$timestamp] [$traceId] [$aplicacao] [$contexto] - ACESSO: " . json_encode($dados, JSON_UNESCAPED_UNICODE) . PHP_EOL;
        file_put_contents($arquivoLog, $linha, FILE_APPEND);
    }
    
    // Registra uma mensagem de log para BitrixHelpers
    public static function logBitrixHelpers(string $mensagem, string $contexto = ''): void
    {
        $arquivoLog = __DIR__ . '/../../../logs/BitrixHelpers.log';
        $timestamp = date('Y-m-d H:i:s');
        $traceId = defined('TRACE_ID') ? TRACE_ID : 'sem_trace';
        $aplicacao = defined('NOME_APLICACAO') ? NOME_APLICACAO : 'desconhecida';

        if (!$contexto) {
            $bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
            $classe = $bt[1]['class'] ?? '';
            $funcao = $bt[1]['function'] ?? 'desconhecido';
            $contexto = $classe ? ($classe . '::' . $funcao) : $funcao;
        }

        $linha = "[$timestamp] [$traceId] [$aplicacao] [$contexto] - $mensagem" . PHP_EOL;
        file_put_contents($arquivoLog, $linha, FILE_APPEND);
    }

    // Registra uma mensagem de log para ClickSign
    public static function logClickSign(string $mensagem, string $contexto = ''): void
    {
        $arquivoLog = __DIR__ . '/../../../logs/clicksign.log';
        $timestamp = date('Y-m-d H:i:s');
        $traceId = defined('TRACE_ID') ? TRACE_ID : 'sem_trace';
        $aplicacao = defined('NOME_APLICACAO') ? NOME_APLICACAO : 'desconhecida';
                
        if (!$contexto) {
            $bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
            $classe = $bt[1]['class'] ?? '';
            $funcao = $bt[1]['function'] ?? 'desconhecido';
            $contexto = $classe ? ($classe . '::' . $funcao) : $funcao;
        }

        $linha = "[$timestamp] [$traceId] [$aplicacao] [$contexto] - $mensagem" . PHP_EOL;
        file_put_contents($arquivoLog, $linha, FILE_APPEND);
    }

    // Registra uma mensagem de log para o SchedulerController
    public static function logSchedulerController(string $mensagem, string $contexto = ''): void
    {
        $arquivoLog = __DIR__ . '/../../../logs/scheduler_controller.log';
        $timestamp = date('Y-m-d H:i:s');
        $traceId = defined('TRACE_ID') ? TRACE_ID : 'sem_trace';
        $aplicacao = defined('NOME_APLICACAO') ? NOME_APLICACAO : 'desconhecida';

        if (!$contexto) {
            $bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
            $classe = $bt[1]['class'] ?? '';
            $funcao = $bt[1]['function'] ?? 'desconhecido';
            $contexto = $classe ? ($classe . '::' . $funcao) : $funcao;
        }

        $linha = "[$timestamp] [$traceId] [$aplicacao] [$contexto] - $mensagem" . PHP_EOL;
        file_put_contents($arquivoLog, $linha, FILE_APPEND);
    }

    // Registra uma mensagem de log para Sincronização Bitrix
    public static function logSincronizacaoBitrix(string $mensagem, string $contexto = ''): void
    {
        $arquivoLog = __DIR__ . '/../../../logs/sincronizacao_bitrix.log';
        $timestamp = date('Y-m-d H:i:s');
        $traceId = defined('TRACE_ID') ? TRACE_ID : 'sem_trace';
        $aplicacao = defined('NOME_APLICACAO') ? NOME_APLICACAO : 'desconhecida';

        if (!$contexto) {
            $bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
            $classe = $bt[1]['class'] ?? '';
            $funcao = $bt[1]['function'] ?? 'desconhecido';
            $contexto = $classe ? ($classe . '::' . $funcao) : $funcao;
        }

        $linha = "[$timestamp] [$traceId] [$aplicacao] [$contexto] - $mensagem" . PHP_EOL;
        file_put_contents($arquivoLog, $linha, FILE_APPEND);
    }

}