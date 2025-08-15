## 🚀 Sistema de Importação de Leads para Bitrix24

Sistema completo de upload e importação de arquivos CSV/Excel para o Bitrix24, com mapeamento de campos, processamento assíncrono via jobs e interface web intuitiva.

### 📋 Funcionalidades
- ✅ Upload de arquivos CSV e Excel
- ✅ Mapeamento interativo de campos do arquivo para campos do Bitrix24
- ✅ Processamento assíncrono via sistema de jobs
- ✅ **Webhooks do banco de dados** - Configuração automática por cliente
- ✅ Interface web responsiva e intuitiva
- ✅ Logs detalhados de importação
- ✅ Validação de dados antes da importação
- ✅ Sistema de rotas integrado ao Apps

### 📁 Estrutura do Projeto
```
├── api/                     # Endpoints REST
│   ├── bitrix_users.php     # Lista usuários do Bitrix
│   ├── confirmacao_import.php # Confirma importação
│   ├── importacao.php       # Upload de arquivos
│   ├── importar_job.php     # Cria job de importação
│   ├── salvar_mapeamento.php # Salva mapeamento
│   └── status_job.php       # Status do job
├── assets/                  # CSS e JavaScript
│   ├── css/importacao.css   # Estilos
│   └── js/                  # Scripts
├── config.php              # Configurações principais + webhook do banco
├── WebhookHelper.php       # Helper para webhooks do banco de dados
├── config_secure.php       # Configurações locais (fallback desenvolvimento)
├── config_secure.php.example # Exemplo de configuração local
├── importacao.php          # Página de upload
├── mapeamento.php          # Página de mapeamento
├── index.php               # Página inicial
├── demo.php               # Demonstração e testes
├── setup.php              # Configuração inicial
└── database.sql           # Estrutura do banco
```

## 🔧 Configuração

### 📊 **Webhooks do Banco de Dados (Produção)**

O sistema busca automaticamente o webhook do banco de dados baseado no cliente:

**URL de Acesso:**
```
/Apps/importar/?cliente=CHAVE_ACESSO_DO_CLIENTE
```

**Fonte dos Dados:**
- Tabela: `cliente_aplicacoes`
- Campos: `webhook_bitrix` para o webhook do Bitrix24
- Slug da aplicação: `'importar'`
- Cliente identificado pela `chave_acesso`

**Exemplo de dados no banco:**
```sql
-- Tabela cliente_aplicacoes
INSERT INTO cliente_aplicacoes 
(cliente_id, aplicacao_id, webhook_bitrix, ativo) 
VALUES 
(1, 5, 'https://cliente.bitrix24.com.br/rest/1/ABC123/', 1);
```

### 🛠️ **Configuração Local (Desenvolvimento)**

Para desenvolvimento local, use o arquivo de fallback:

1. **Copie o arquivo exemplo:**
   ```bash
   cp config_secure.php.example config_secure.php
   ```

2. **Configure no arquivo `config_secure.php`:**
   ```php
   return [
       'bitrix_webhook' => 'https://seudominio.bitrix24.com.br/rest/1/WEBHOOK_LOCAL/',
       'ambiente' => 'development'
   ];
   ```

3. **Defina variável de ambiente:**
   ```bash
   # No seu .env ou sistema
   APP_ENV=development
   ```

## 🌐 **Sistema de Rotas**

O sistema está integrado ao roteador unificado do Apps:

### URLs Disponíveis:
```
# Páginas principais
/Apps/importar/                    → Página inicial
/Apps/importar/importacao         → Upload de arquivos  
/Apps/importar/mapeamento         → Mapeamento de campos
/Apps/importar/demo               → Demonstração e testes

# API Endpoints
/Apps/importar/api/importacao     → Upload via API
/Apps/importar/api/bitrix_users   → Lista usuários Bitrix
/Apps/importar/api/importar_job   → Cria job de importação
/Apps/importar/api/status_job     → Status do job
/Apps/importar/api/salvar_mapeamento → Salva mapeamento
/Apps/importar/api/confirmacao_import → Confirma importação
```

### Compatibilidade com URLs Antigas:
O sistema mantém compatibilidade com endpoints antigos do FastRoute:
- `importar_async` → redireciona para `importar_job`
- `status_importacao` → redireciona para `status_job`
- `importar_batch` → redireciona para `importar_job`
- `status_batch` → redireciona para `status_job`

## 🚦 **Como Usar**

### 1. **Acesso com Cliente**
```
https://seudominio.com/Apps/importar/?cliente=CHAVE_DO_CLIENTE
```

### 2. **Fluxo de Importação**
1. **Upload** → Envie arquivo CSV/Excel
2. **Mapeamento** → Associe colunas aos campos do Bitrix
3. **Processamento** → Job assíncrono processa os dados
4. **Monitoramento** → Acompanhe status via API

### 3. **Demonstração/Teste**
```
https://seudominio.com/Apps/importar/demo
```

## 🔒 **Segurança**

- ✅ **Webhooks no banco** - Configuração centralizada por cliente
- ✅ **Arquivos sensíveis** - `.gitignore` protege configs locais
- ✅ **Validação de webhook** - Verifica URL e domínio Bitrix
- ✅ **Logs de erro** - Sistema de logging integrado
- ✅ **Autenticação** - Sistema de clientes do Apps

## 🧪 **Testes e Demonstração**

O arquivo `demo.php` permite testar:
- ✅ Conexão com banco de dados
- ✅ Webhook do Bitrix (se configurado)
- ✅ Helpers e dependências
- ✅ Status da configuração atual

## 📝 **Logs**

Logs são salvos em:
- `logs/batch_jobs.log` - Jobs de importação
- Error log do sistema - Erros de webhook/conexão

## ⚡ **Tecnologias**

- **PHP 8.x** - Backend
- **MySQL** - Banco de dados  
- **BitrixHelper** - API Bitrix24
- **Sistema Jobs** - Processamento assíncrono
- **CSS/JS** - Interface responsiva
- **Roteamento Apps** - Sistema unificado de rotas
