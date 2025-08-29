<?php
namespace Repositories;

require_once __DIR__ . '/../config/config_KW24DadosBitrix.php';

class KW24DadosBitrixDAO 
{
    private $pdo;
    
    public function __construct() 
    {
        $config = require __DIR__ . '/../config/config_KW24DadosBitrix.php';
        
        try {
            $dsn = "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4";
            $this->pdo = new \PDO($dsn, $config['usuario'], $config['senha']);
            $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        } catch (\PDOException $e) {
            throw new \Exception("Erro na conexão com banco: " . $e->getMessage());
        }
    }
    
    /**
     * Verifica se a tabela bitrix_field_mapping existe
     */
    public function tabelaDicionarioExiste() 
    {
        try {
            $sql = "SHOW TABLES LIKE 'bitrix_field_mapping'";
            $stmt = $this->pdo->query($sql);
            return $stmt->rowCount() > 0;
        } catch (\PDOException $e) {
            throw new \Exception("Erro ao verificar existência da tabela: " . $e->getMessage());
        }
    }
    
    /**
     * Cria a tabela bitrix_field_mapping do zero
     */
    public function criarTabelaDicionario() 
    {
        try {
            $sql = "
                CREATE TABLE IF NOT EXISTS bitrix_field_mapping (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    uf_field VARCHAR(255) NOT NULL,
                    friendly_name VARCHAR(255) NOT NULL,
                    entity_type ENUM('cards', 'tasks', 'companies', 'colaboradores') NOT NULL,
                    field_type VARCHAR(100) DEFAULT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_field_entity (uf_field, entity_type)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ";
            
            $this->pdo->exec($sql);
            return true;
        } catch (\PDOException $e) {
            throw new \Exception("Erro ao criar tabela dicionário: " . $e->getMessage());
        }
    }
    
    /**
     * Insere múltiplos campos no dicionário
     */
    public function inserirCamposDicionario($campos) 
    {
        if (empty($campos)) {
            return true;
        }
        
        try {
            $sql = "
                INSERT IGNORE INTO bitrix_field_mapping 
                (uf_field, friendly_name, entity_type, field_type) 
                VALUES (?, ?, ?, ?)
            ";
            
            $stmt = $this->pdo->prepare($sql);
            
            foreach ($campos as $campo) {
                $stmt->execute([
                    $campo['uf_field'],
                    $campo['friendly_name'],
                    $campo['entity_type'],
                    $campo['field_type'] ?? null
                ]);
            }
            
            return true;
        } catch (\PDOException $e) {
            throw new \Exception("Erro ao inserir campos no dicionário: " . $e->getMessage());
        }
    }
    
    /**
     * Método genérico para consultar dados da tabela dicionário
     */
    public function consultarDicionario($filtros = [], $campos = ['*']) 
    {
        try {
            $sql = "SELECT " . implode(', ', $campos) . " FROM bitrix_field_mapping";
            $parametros = [];
            
            if (!empty($filtros)) {
                $condicoes = [];
                foreach ($filtros as $campo => $valor) {
                    $condicoes[] = "$campo = ?";
                    $parametros[] = $valor;
                }
                $sql .= " WHERE " . implode(' AND ', $condicoes);
            }
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($parametros);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            throw new \Exception("Erro ao consultar dicionário: " . $e->getMessage());
        }
    }
    
    /**
     * Consulta todos os campos atuais do dicionário
     * @deprecated Use consultarDicionario() instead
     */
    public function consultarCamposAtuaisDicionario() 
    {
        return $this->consultarDicionario();
    }
    
    /**
     * Consulta campos do dicionário por tipo de entidade
     * @deprecated Use consultarDicionario(['entity_type' => $entityType]) instead
     */
    public function consultarCamposPorTipo($entityType) 
    {
        return $this->consultarDicionario(['entity_type' => $entityType], ['uf_field', 'friendly_name', 'field_type']);
    }
    
    /**
     * Remove campos que não existem mais no Bitrix
     * Aceita tanto array simples de uf_fields quanto array de chaves compostas 'uf_field|entity_type'
     */
    public function removerCamposObsoletos($camposParaRemover) 
    {
        if (empty($camposParaRemover)) {
            return true;
        }
        
        try {
            // Detectar se são chaves compostas (uf_field|entity_type) ou só uf_fields
            $primeiroItem = reset($camposParaRemover);
            
            if (strpos($primeiroItem, '|') !== false) {
                // Chaves compostas - remover por uf_field E entity_type
                $condicoes = [];
                $parametros = [];
                
                foreach ($camposParaRemover as $chave) {
                    $partes = explode('|', $chave);
                    $condicoes[] = "(uf_field = ? AND entity_type = ?)";
                    $parametros[] = $partes[0]; // uf_field
                    $parametros[] = $partes[1]; // entity_type
                }
                
                $sql = "DELETE FROM bitrix_field_mapping WHERE " . implode(' OR ', $condicoes);
                
            } else {
                // Array simples - remover só por uf_field (compatibilidade retroativa)
                $placeholders = str_repeat('?,', count($camposParaRemover) - 1) . '?';
                $sql = "DELETE FROM bitrix_field_mapping WHERE uf_field IN ($placeholders)";
                $parametros = $camposParaRemover;
            }
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($parametros);
            
            return true;
        } catch (\PDOException $e) {
            throw new \Exception("Erro ao remover campos obsoletos: " . $e->getMessage());
        }
    }
    
    /**
     * Deleta completamente a tabela dicionário (para testes)
     */
    public function deletarTabelaDicionario() 
    {
        try {
            $sql = "DROP TABLE IF EXISTS bitrix_field_mapping";
            $this->pdo->exec($sql);
            return true;
        } catch (\PDOException $e) {
            throw new \Exception("Erro ao deletar tabela dicionário: " . $e->getMessage());
        }
    }
    
    /**
     * Executa SQL direto (para limpeza de tabelas)
     */
    public function executarSQL($sql) 
    {
        try {
            $this->pdo->exec($sql);
            return true;
        } catch (\PDOException $e) {
            throw new \Exception("Erro ao executar SQL: " . $e->getMessage());
        }
    }
    
    /**
     * Verifica se uma tabela específica existe
     */
    public function tabelaExiste($nomeTabela) 
    {
        try {
            $sql = "SHOW TABLES LIKE ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$nomeTabela]);
            return $stmt->rowCount() > 0;
        } catch (\PDOException $e) {
            throw new \Exception("Erro ao verificar existência da tabela $nomeTabela: " . $e->getMessage());
        }
    }
    
    /**
     * Consulta campos do dicionário por entity_type
     */
    public function consultarCamposPorEntidade($entityType) 
    {
        return $this->consultarDicionario(['entity_type' => $entityType], ['uf_field', 'friendly_name', 'field_type']);
    }
    
    /**
     * Cria tabela com colunas baseadas no dicionário
     */
    public function criarTabelaComColunas($nomeTabela, $campos) 
    {
        try {
            if (empty($campos)) {
                throw new \Exception("Lista de campos não pode estar vazia");
            }
            
            // Verificar se existe campo 'id' no dicionário para usar como chave primária
            $temCampoId = false;
            foreach ($campos as $campo) {
                if ($campo['uf_field'] === 'id') {
                    $temCampoId = true;
                    break;
                }
            }
            
            // Montar SQL de criação da tabela
            $sql = "CREATE TABLE IF NOT EXISTS `$nomeTabela` (\n";
            
            // Adicionar colunas baseadas nos friendly_name
            foreach ($campos as $campo) {
                $nomeColuna = $this->sanitizarNomeColuna($campo['friendly_name']);
                $tipoColuna = $this->mapearTipoColuna($campo['field_type'] ?? null);
                
                // Se for o campo id, torná-lo chave primária
                if ($campo['uf_field'] === 'id') {
                    $sql .= "    `$nomeColuna` $tipoColuna NOT NULL PRIMARY KEY,\n";
                } else {
                    $sql .= "    `$nomeColuna` $tipoColuna DEFAULT NULL,\n";
                }
            }
            
            // Adicionar bitrix_id apenas se não temos campo id do dicionário
            if (!$temCampoId) {
                $sql .= "    bitrix_id VARCHAR(50) DEFAULT NULL,\n";
                $sql .= "    UNIQUE KEY unique_bitrix_id (bitrix_id),\n";
            }
            
            $sql .= "    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\n";
            $sql .= "    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP\n";
            $sql .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            $this->pdo->exec($sql);
            
            return [
                'sucesso' => true,
                'tabela' => $nomeTabela,
                'colunas_criadas' => count($campos)
            ];
            
        } catch (\PDOException $e) {
            return [
                'sucesso' => false,
                'erro' => "Erro ao criar tabela $nomeTabela: " . $e->getMessage()
            ];
        }
    }
    
    /**
     * Sanitiza nome da coluna para uso no MySQL - versão preservativa
     */
    private function sanitizarNomeColuna($nome) 
    {
        // Apenas converter espaços para underscores
        $nome = str_replace(' ', '_', $nome);
        
        // Remover apenas caracteres que realmente causam problemas no MySQL
        // Manter #, -, +, %, e outros caracteres especiais importantes
        $nome = preg_replace('/[`"\'\\\\]/', '', $nome);
        
        // Garantir que não comece com número
        if (preg_match('/^[0-9]/', $nome)) {
            $nome = 'campo_' . $nome;
        }
        
        // Garantir tamanho máximo (MySQL limit é 64 caracteres)
        if (strlen($nome) > 64) {
            $nome = substr($nome, 0, 60) . '_' . substr(md5($nome), 0, 3);
        }
        
        // Se ficou vazio, usar fallback
        if (empty(trim($nome))) {
            $nome = 'campo_' . md5($nome);
        }
        
        return $nome;
    }
    
    /**
     * Mapeia tipo do Bitrix para tipo MySQL
     */
    private function mapearTipoColuna($tipoBitrix) 
    {
        switch ($tipoBitrix) {
            case 'integer':
            case 'int':
                return 'INT';
            case 'double':
            case 'float':
                return 'DECIMAL(15,2)';
            case 'datetime':
                return 'DATETIME';
            case 'date':
                return 'DATE';
            case 'boolean':
            case 'bool':
                return 'TINYINT(1)';
            case 'enumeration':
            case 'crm_status':
                return 'VARCHAR(255)';
            case 'text':
                return 'TEXT';
            case 'money':
                return 'DECIMAL(15,2)';
            case 'user':
            case 'employee':
                return 'INT';
            case 'file':
            case 'url':
                return 'TEXT';
            default:
                return 'VARCHAR(500)'; // Default para campos de texto
        }
    }
}
