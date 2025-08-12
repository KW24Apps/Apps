# 📋 **CÓDIGOS DE RETORNO - CLICKSIGN**

> **Documentação dos códigos de retorno específicos do sistema ClickSign**  
> **Localização:** `/apis.kw24.com.br/Apps/docs/`  
> **Relacionado:** `codigos-retorno-sistema.md` (códigos gerais)

---

## 🎛️ **CS - CLICKSIGN CONTROLLER**

### **CS0XX - Validações de Entrada**
- **CS001** - `Parâmetros obrigatórios ausentes`
- **CS002** - `Acesso não autorizado ou incompleto`
- **CS003** - `Deal não encontrado`
- **CS004** - `Arquivo a ser assinado é obrigatório`
- **CS005** - `Data limite de assinatura é obrigatória`
- **CS006** - `Data limite deve ser posterior à data atual`
- **CS007** - `Pelo menos um signatário deve estar configurado`
- **CS008** - `Campos obrigatórios ausentes ou inválidos: {detalhes}`

### **CS1XX - Validações de Signatários**
- **CS101** - `Dados faltantes nos signatários ({papel})`
- **CS102** - `Dados do signatário incompletos`
- **CS103** - `Dados do signatário não encontrados no evento`

### **CS2XX - Processamento de Arquivos**
- **CS201** - `Erro ao converter o arquivo`
- **CS202** - `Falha ao converter o arquivo`
- **CS203** - `Erro ao baixar/converter arquivo para anexo no negócio`

### **CS3XX - Operações ClickSign**
- **CS301** - `Falha ao criar signatário ({papel})`
- **CS302** - `Falha ao vincular signatário ({papel})`
- **CS303** - `Documento criado, mas houve falha em um ou mais vínculos de signatários`

### **CS4XX - Sucessos Principais**
- **CS401** - `Documento enviado para assinatura`
- **CS402** - `Assinatura realizada por {nome} - {email}`
- **CS403** - `Documento assinado com sucesso`
- **CS404** - `Documento assinado e arquivo enviado para o Bitrix`
- **CS405** - `Arquivo baixado, anexado e mensagem atualizada no Bitrix`

### **CS5XX - Estados de Documento**
- **CS501** - `Assinatura cancelada por prazo finalizado`
- **CS502** - `Assinatura cancelada manualmente`
- **CS503** - `Evento auto_close salvo, aguardando document_closed`
- **CS504** - `Evento {evento} processado com atualização imediata no Bitrix`
- **CS505** - `Mensagem final enviada (sem anexo de arquivo)`

### **CS6XX - Controle de Duplicatas**
- **CS601** - `Assinatura já processada`
- **CS602** - `Evento duplicado ignorado ({evento})`
- **CS603** - `Documento já disponível, evento duplicado ignorado`

### **CS7XX - Webhooks e Retornos**
- **CS701** - `Parâmetros obrigatórios ausentes para processar assinatura`
- **CS702** - `Assinatura HMAC inválida`
- **CS703** - `Documento não encontrado`
- **CS704** - `Campo retorno não encontrado na assinatura`
- **CS705** - `Evento recebido sem ação específica`

### **CS8XX - Processamento de Status**
- **CS801** - `Parâmetros básicos obrigatórios ausentes`
- **CS802** - `Campo retorno obrigatório ausente`
- **CS803** - `StatusClosed não encontrado após {tentativas} tentativas`
- **CS804** - `Documento cancelado, evento ignorado`
- **CS805** - `Status inesperado encontrado`

### **CS9XX - Erros de Controle**
- **CS901** - `Assinatura criada, mas falha ao gravar no banco`
- **CS902** - `Documento finalizado, mas erro ao gravar controle de assinatura`
- **CS903** - `Nenhum campo para atualizar`
- **CS904** - `Token ClickSign ausente. Não é possível baixar o arquivo assinado`

---

## 🔧 **CH - CLICKSIGN HELPER**

### **CH0XX - Erros de Comunicação**
- **CH001** - `Endpoint não é string`
- **CH002** - `Erro cURL: {erro}`
- **CH003** - `Token ClickSign não configurado`
- **CH004** - `Resposta vazia da API ClickSign`

### **CH1XX - Validações HMAC**
- **CH101** - `Secret não configurado para validação HMAC`
- **CH102** - `Hash HMAC inválido`
- **CH103** - `Parâmetros inválidos para validação HMAC`

### **CH2XX - Operações de Documento**
- **CH201** - `Erro ao criar documento na ClickSign`
- **CH202** - `Erro ao buscar documento na ClickSign`
- **CH203** - `Documento não encontrado na ClickSign`

### **CH3XX - Operações de Signatário**
- **CH301** - `Erro ao criar signatário na ClickSign`
- **CH302** - `Erro ao vincular signatário na ClickSign`
- **CH303** - `Erro ao enviar notificação na ClickSign`

---

## 📊 **TABELA DE PRIORIDADES**

| **Código** | **Tipo** | **Ação** |
|------------|----------|----------|
| CS0XX, CS1XX | ❌ **Erro Crítico** | Parar execução |
| CS2XX, CS3XX | ⚠️ **Erro Operacional** | Log + retry |
| CS4XX | ✅ **Sucesso** | Continuar |
| CS5XX, CS6XX | ℹ️ **Informativo** | Log apenas |
| CS7XX+ | 🔄 **Controle** | Decisão baseada em contexto |

---

## 🎯 **IMPLEMENTAÇÃO RECOMENDADA**

### **1. Constantes por Arquivo:**
```php
// ClickSignController.php
class ClickSignCodes {
    const PARAMS_AUSENTES = 'CS001';
    const ACESSO_NAO_AUTORIZADO = 'CS002';
    const DEAL_NAO_ENCONTRADO = 'CS003';
    const ARQUIVO_OBRIGATORIO = 'CS004';
    const DATA_LIMITE_OBRIGATORIA = 'CS005';
    const DATA_LIMITE_PASSADO = 'CS006';
    const SIGNATARIO_OBRIGATORIO = 'CS007';
    const CAMPOS_INVALIDOS = 'CS008';
    
    // Sucessos
    const DOCUMENTO_ENVIADO = 'CS401';
    const ASSINATURA_REALIZADA = 'CS402';
    const DOCUMENTO_ASSINADO = 'CS403';
    const ARQUIVO_ENVIADO_BITRIX = 'CS404';
    // ... etc
}

// ClickSignHelper.php  
class ClickSignHelperCodes {
    const ENDPOINT_INVALIDO = 'CH001';
    const CURL_ERRO = 'CH002';
    const TOKEN_NAO_CONFIGURADO = 'CH003';
    const RESPOSTA_VAZIA = 'CH004';
    
    const SECRET_NAO_CONFIGURADO = 'CH101';
    const HMAC_INVALIDO = 'CH102';
    // ... etc
}
```

### **2. Método de Formatação:**
```php
public static function formatarRetornoClickSign($codigo, $detalhes = null) {
    $mensagens = [
        // Validações críticas
        'CS001' => 'Parâmetros obrigatórios ausentes',
        'CS002' => 'Acesso não autorizado ou incompleto',
        'CS003' => 'Deal não encontrado',
        
        // Sucessos
        'CS401' => 'Documento enviado para assinatura',
        'CS402' => 'Assinatura realizada por {nome} - {email}',
        'CS403' => 'Documento assinado com sucesso',
        
        // Helpers
        'CH001' => 'Endpoint não é string',
        'CH002' => 'Erro cURL: {erro}',
        'CH003' => 'Token ClickSign não configurado',
        
        // ... mapa completo dos códigos CS/CH
    ];
    
    $mensagem = $mensagens[$codigo] ?? 'Código ClickSign não encontrado';
    
    // Substituição de variáveis {nome}, {email}, {erro}, etc.
    if ($detalhes && strpos($mensagem, '{') !== false) {
        foreach ($detalhes as $key => $value) {
            $mensagem = str_replace("{{$key}}", $value, $mensagem);
        }
    }
    
    return [
        'code' => $codigo,
        'message' => $mensagem,
        'success' => substr($codigo, 2, 1) === '4', // CS4XX = sucesso
        'category' => substr($codigo, 0, 2), // CS ou CH
        'severity' => self::getSeverity($codigo)
    ];
}

private static function getSeverity($codigo) {
    $range = substr($codigo, 2, 1);
    switch($range) {
        case '0':
        case '1': return 'critical';
        case '2':
        case '3': return 'error';
        case '4': return 'success';
        case '5':
        case '6': return 'info';
        default: return 'warning';
    }
}
```

### **3. Integração com WhatsApp:**
```php
// Novos códigos para WhatsApp (a serem adicionados)
const WHATSAPP_TELEFONE_OBRIGATORIO = 'CS051';
const WHATSAPP_FORMATO_INVALIDO = 'CS052';
const WHATSAPP_NOTIFICACAO_ENVIADA = 'CS451';
const WHATSAPP_ASSINATURA_INICIADA = 'CS452';
```

---

## 🔄 **FLUXO DE IMPLEMENTAÇÃO**

1. **Fase 1**: Implementar constantes nos controllers/helpers
2. **Fase 2**: Substituir strings hardcoded pelos códigos
3. **Fase 3**: Implementar método de formatação
4. **Fase 4**: Adicionar códigos WhatsApp conforme desenvolvimento
5. **Fase 5**: Integrar com sistema de logs centralizado

---

## 📚 **REFERÊNCIAS**

- **Arquivo Relacionado**: `codigos-retorno-sistema.md` (códigos gerais BH, DA, UT, etc.)
- **Controllers**: `ClickSignController.php`
- **Helpers**: `ClickSignHelper.php`
- **APIs**: ClickSign V1 (documents) + V2 (envelopes/WhatsApp)

---

*Última atualização: Dezembro 2024*  
*Mantenha este arquivo versionado no Git para controle de mudanças*
