# 📋 **CÓDIGOS DE RETORNO - SISTEMA GERAL**

## 🎯 **OBJETIVO**
Padronizar todos os retornos dos helpers gerais do sistema com códigos únicos, organizados por camada/responsabilidade.

---

## 📁 **ESTRUTURA DE CÓDIGOS**

### **Formato:** `[CATEGORIA][SUBCATEGORIA][NÚMERO]`
- **BH** = Bitrix Helper (geral)
- **DA** = Database Access (DAO)
- **UT** = Utilities Helper
- **BT** = Bitrix Task Helper
- **BC** = Bitrix Contact Helper
- **BD** = Bitrix Deal Helper
- **BE** = Bitrix Company Helper

---

## � **BH - BITRIX HELPER (GERAL)**

### **BH0XX - Erros de Comunicação**
- **BH001** - `Webhook não informado`
- **BH002** - `Erro cURL na comunicação com Bitrix`
- **BH003** - `Resposta inválida da API Bitrix`
- **BH004** - `Token Bitrix não configurado`

### **BH1XX - Operações CRM**
- **BH101** - `Erro ao consultar deal no Bitrix`
- **BH102** - `Erro ao editar deal no Bitrix`
- **BH103** - `Erro ao consultar contato no Bitrix`
- **BH104** - `Deal não encontrado no Bitrix`
- **BH105** - `Contato não encontrado no Bitrix`
- **BH106** - `Erro desconhecido ao listar itens CRM`

### **BH2XX - Comentários Timeline**
- **BH201** - `Erro ao adicionar comentário na timeline`
- **BH202** - `Erro ao atualizar comentário na timeline`
- **BH203** - `EntityTypeId não informado para comentário`

---

## 🎯 **BD - BITRIX DEAL HELPER**

### **BD0XX - Validações**
- **BD001** - `Parâmetros obrigatórios não informados`
- **BD002** - `EntityId não informado`
- **BD003** - `DealId não informado`

### **BD1XX - Operações de Deal**
- **BD101** - `Erro desconhecido ao criar negócio`
- **BD102** - `Erro desconhecido ao editar negócio`

---

## 📞 **BC - BITRIX CONTACT HELPER**

### **BC0XX - Validações**
- **BC001** - `Parâmetros obrigatórios não informados`
- **BC002** - `ID do contato não informado`

### **BC1XX - Operações de Contato**
- **BC101** - `Contato não encontrado`
- **BC102** - `Erro ao consultar contato`

---

## 🏢 **BE - BITRIX COMPANY HELPER**

### **BE0XX - Validações**
- **BE001** - `Parâmetros obrigatórios não informados`
- **BE002** - `ID da empresa não informado`

### **BE1XX - Operações de Empresa**
- **BE101** - `Empresa não encontrada`
- **BE102** - `Erro ao consultar empresa`

---

## ✅ **BT - BITRIX TASK HELPER**

### **BT0XX - Validações**
- **BT001** - `O título da tarefa é obrigatório`
- **BT002** - `O responsável pela tarefa é obrigatório`
- **BT003** - `O criador da tarefa é obrigatório`
- **BT004** - `Parâmetros obrigatórios não informados`
- **BT005** - `ID da tarefa não informado`

### **BT1XX - Operações de Tarefa**
- **BT101** - `Erro desconhecido ao criar tarefa`
- **BT102** - `Erro desconhecido ao editar tarefa`
- **BT103** - `Erro desconhecido ao listar tarefas`
- **BT104** - `Erro ao consultar campos de tarefas`

---

## 🗄️ **DA - DATABASE ACCESS (DAO)**

### **DA0XX - Erros de Conexão**
- **DA001** - `Erro de conexão com banco de dados`
- **DA002** - `Configuração de banco não encontrada`

### **DA1XX - Operações Gerais**
- **DA101** - `Erro DB genérico`
- **DA102** - `Registro não encontrado`
- **DA103** - `Erro PDO na consulta`
- **DA104** - `Erro geral na operação`

### **DA2XX - Operações Específicas**
- **DA201** - `Erro PDO ao inserir em assinaturas_clicksign`
- **DA202** - `Erro geral ao salvar assinatura`
- **DA203** - `Erro PDO ao atualizar status_closed`
- **DA204** - `Erro geral ao atualizar status_closed`
- **DA205** - `Erro PDO ao obter assinatura completa`
- **DA206** - `Erro geral ao obter assinatura completa`
- **DA207** - `Assinatura não encontrada no banco`
- **DA208** - `Dados de assinatura inconsistentes`

### **DA3XX - Operações de Log**
- **DA301** - `Erro ao gravar log de acesso`
- **DA302** - `Erro ao consultar logs`
- **DA303** - `Tabela de log não encontrada`

---

## 🛠️ **UT - UTILITIES HELPER**

### **UT0XX - Validações**
- **UT001** - `Parâmetros obrigatórios não informados`
- **UT002** - `Formato de data inválido`
- **UT003** - `Formato de email inválido`
- **UT004** - `Formato de telefone inválido`

### **UT1XX - Conversões e Formatações**
- **UT101** - `Erro ao converter arquivo`
- **UT102** - `Erro ao formatar data`
- **UT103** - `Erro ao validar CPF/CNPJ`
- **UT104** - `Erro ao gerar hash`

### **UT2XX - Operações de Arquivo**
- **UT201** - `Arquivo não encontrado`
- **UT202** - `Erro ao ler arquivo`
- **UT203** - `Erro ao escrever arquivo`
- **UT204** - `Permissão negada para arquivo`

---

## 📊 **COMO USAR ESTA DOCUMENTAÇÃO**

1. **Identificação**: Use o prefixo da categoria para identificar rapidamente a origem do erro
2. **Debugging**: O código numérico ajuda a localizar o ponto exato do problema
3. **Logs**: Sempre registre o código de retorno nos logs para facilitar troubleshooting
4. **Documentação**: Mantenha este arquivo atualizado conforme novos códigos são adicionados

---

*Última atualização: Dezembro 2024*
*Mantenha este arquivo versionado no Git para controle de mudanças*
