<?php
/**
 * Teste SIMPLES - Chama apenas executar() como seria em produção
 * Comando: php teste_simples.php
 */

// Incluir arquivos necessários
require_once __DIR__ . '/controllers/KW24DadosBitrix.php';

use Controllers\KW24DadosBitrix;

echo "=== TESTE SIMPLES - SIMULAÇÃO REAL ===\n";
echo "Executando sincronização completa...\n\n";

try {
    // Instanciar classe
    $controller = new KW24DadosBitrix();
    
    // CHAMADA ÚNICA - igual seria em produção
    echo "Executando sincronização...\n";
    $resultado = $controller->executar();
    
    if (!$resultado['sucesso']) {
        throw new Exception('Erro na sincronização: ' . $resultado['erro']);
    }
    
    echo "✅ SINCRONIZAÇÃO CONCLUÍDA COM SUCESSO!\n\n";
    
    // Mostrar resumo dos resultados
    if (isset($resultado['detalhes'])) {
        echo "=== RESUMO DOS RESULTADOS ===\n";
        
        if (isset($resultado['detalhes']['dicionario'])) {
            $dic = $resultado['detalhes']['dicionario'];
            echo "📊 TABELA DICIONÁRIO:\n";
            echo "   - Tabela já existia: " . ($dic['tabela_existia'] ? 'Sim' : 'Não') . "\n";
            echo "   - Total de campos: " . $dic['total_campos'] . "\n";
            if (isset($dic['detalhes'])) {
                echo "   - Cards: " . $dic['detalhes']['cards'] . " campos\n";
                echo "   - Tasks: " . $dic['detalhes']['tasks'] . " campos\n";
                echo "   - Companies: " . $dic['detalhes']['companies'] . " campos\n";
            }
            echo "\n";
        }
    }
    
    echo "✅ Tudo funcionando como em produção!\n";
    echo "💡 Próximo passo: Implementar PASSO 3 (Tabelakw24, tabelacliente, tabelatarefas)\n";
    
} catch (Exception $e) {
    echo "❌ ERRO na sincronização: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== FIM DO TESTE ===\n";
