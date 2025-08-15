# Sistema de Importação de Leads - KW24

Sistema de importação de dados de planilhas (CSV/Excel) para o Bitrix24, com processamento assíncrono via sistema de jobs.

## 📁 Estrutura do Projeto

```
Apps/public/form/
├── index.php              # Página inicial
├── importacao.php          # Formulário de importação
├── mapeamento.php          # Mapeamento de campos
├── config.php              # Configurações gerais (SEM webhook)
├── config_secure.php       # Configurações sensíveis (webhook) - NÃO commitado
├── config_secure.php.example # Exemplo de configuração segura
├── .gitignore              # Protege arquivos sensíveis
├── assets/
│   ├── css/
│   │   └── importacao.css  # Estilos do formulário
│   └── js/
│       ├── importacao.js   # JavaScript do formulário
│       └── confirmacao.js  # JavaScript do modal de confirmação
├── api/
│   ├── importacao.php      # Processa upload de arquivos
│   ├── bitrix_users.php    # Busca usuários do Bitrix
│   ├── salvar_mapeamento.php # Salva mapeamento na sessão
│   ├── confirmacao_import.php # Dados para confirmação
│   ├── importar_job.php    # Cria job na fila
│   └── status_job.php      # Consulta status do job
├── uploads/                # Arquivos temporários
└── logs/                   # Logs do sistema
```

## 🚀 Funcionalidades

- **Upload de Arquivos**: Suporte a CSV e Excel
- **Mapeamento Automático**: Associação automática de colunas com campos do Bitrix
- **Sistema de Jobs**: Processamento assíncrono em segundo plano
- **Interface Responsiva**: Design moderno e intuitivo
- **Integração com Bitrix24**: Usa BitrixDealHelper atualizado
- **Autocomplete de Usuários**: Busca dinâmica de usuários do Bitrix

## ⚙️ Configuração

### 🔒 **Configuração Segura de Webhooks**

O sistema usa um arquivo separado para configurações sensíveis:

1. **Copie o arquivo exemplo:**
   ```bash
   cp config_secure.php.example config_secure.php
   ```

2. **Configure seus webhooks no arquivo `config_secure.php`:**
   ```php
   // Para ambiente local/desenvolvimento
   'bitrix_webhook' => 'https://seubitrix.bitrix24.com.br/rest/USER/WEBHOOK_LOCAL/',
   
   // Para ambiente de produção  
   'bitrix_webhook' => 'https://seubitrix.bitrix24.com.br/rest/USER/WEBHOOK_PRODUCAO/',
   ```

3. **O arquivo `config_secure.php` nunca será commitado no git** (está no .gitignore)

### 🌍 **Controle por Ambiente**

O sistema detecta automaticamente o ambiente via variável `APP_ENV`:
- **Local**: `APP_ENV=local` (ou não definida)
- **Produção**: `APP_ENV=production`

### 📋 **Configuração de Funis**
### 📋 **Configuração de Funis**

Configure os funis no arquivo `config.php`:
   ```php
   $FUNIS_DISPONIVEIS = [
       '2' => 'Negócios',
       '84' => 'Postagens e Avisos',
       // Adicione mais conforme necessário
   ];
   ```

### 🔧 **Configuração Automática**

Alternativamente, use o setup automático:
```
http://seu-dominio/Apps/public/form/setup.php
```

3. **Permissões**: Certifique-se que as pastas `uploads/` e `logs/` tenham permissão de escrita.

## 🔄 Fluxo de Funcionamento

1. **Upload**: Usuário faz upload do arquivo CSV/Excel
2. **Mapeamento**: Sistema apresenta campos para mapeamento automático
3. **Confirmação**: Modal mostra preview dos dados a serem importados
4. **Job Creation**: Sistema cria job na fila usando `BitrixDealHelper::criarJobParaFila()`
5. **Processamento**: Job é processado em segundo plano pelo sistema de batch jobs

## 🔗 Dependências

O projeto utiliza recursos da pasta `Apps/`:

- `Apps/helpers/BitrixHelper.php` - Helper para API do Bitrix
- `Apps/helpers/BitrixDealHelper.php` - Helper específico para deals
- `Apps/dao/BatchJobDAO.php` - DAO para gerenciamento de jobs
- `Apps/config/configdashboard.php` - Configurações do banco de dados

## 📋 API Endpoints

- `POST api/importacao.php` - Processa upload de arquivo
- `GET api/bitrix_users.php?q={query}` - Busca usuários do Bitrix
- `POST api/salvar_mapeamento.php` - Salva mapeamento de campos
- `GET api/confirmacao_import.php` - Dados para confirmação
- `POST api/importar_job.php` - Cria job na fila
- `GET api/status_job.php?job_id={id}` - Consulta status do job

## 🎨 Interface

- Design moderno com tema azul/branco
- Componentes de upload personalizados
- Autocomplete para seleção de usuários
- Modais para confirmação e acompanhamento
- Responsivo para desktop e mobile

## � **Segurança**

### 🛡️ **Proteção de Webhooks**
- Webhooks ficam em arquivo separado (`config_secure.php`)
- Arquivo nunca é commitado no git (protegido por .gitignore)
- Interface de usuário nunca mostra webhook completo
- Sistema de ambientes (local/produção) com webhooks diferentes

### 📁 **Arquivos Protegidos**
- `config_secure.php` - Configurações sensíveis
- `uploads/*` - Arquivos temporários de upload  
- `logs/*` - Logs do sistema
- `.env` - Variáveis de ambiente

### 🚫 **Informações Ocultas**
- Webhook só mostra primeiros 25 e últimos 10 caracteres
- Demos mostram ambiente sem expor webhook completo
- Setup salva webhook de forma segura

## �🔧 Personalização

Para adicionar novos funis:
1. Edite o array `$FUNIS_DISPONIVEIS` em `config.php`
2. O sistema automaticamente incluirá nas opções do formulário

Para alterar o webhook:
1. Modifique a constante `BITRIX_WEBHOOK` em `config.php`
2. Todos os arquivos utilizarão automaticamente a nova configuração

## 📝 Logs

O sistema mantém logs de debug em:
- `logs/batch_file_debug.log` - Logs gerais do sistema
- Logs do BitrixHelper conforme configurado na pasta Apps

## 🆕 Melhorias Implementadas

- ✅ Uso do sistema de jobs em vez de processamento síncrono
- ✅ Configuração centralizada
- ✅ Interface moderna e responsiva
- ✅ Mapeamento automático de campos
- ✅ Processamento em lotes (batch)
- ✅ Tratamento de erros aprimorado
- ✅ Compatibilidade com BitrixDealHelper atualizado
