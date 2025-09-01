# Mapa de Funções Atuais - ClickSign

Este documento mapeia o fluxo de execução exato a partir de cada chamada no `ClickSignController` para as funções dentro do `ClickSignService.php`.

---

### 1. Fluxo: `GerarAssinatura()`

Inicia o processo de criação e envio de um documento para assinatura.

```
ClickSignController::GerarAssinatura()
└── ClickSignService::gerarAssinatura()
    ├── self::validarCamposEssenciais()
    ├── self::processarSignatarios()
    ├── self::processarArquivo()
    ├── self::vincularSignatarios()
    ├── self::registrarAssinaturaComRetry()
    ├── self::atualizarCamposSignatariosBitrix()
    └── self::atualizarRetornoBitrix()
```

---

### 2. Fluxo: `retornoClickSign()` (Webhook)

Processa os eventos (webhooks) enviados pela ClickSign.

```
ClickSignController::retornoClickSign()
└── ClickSignService::processarWebhook()
    ├── (evento: 'sign')
    │   └── self::assinaturaRealizada()
    │       └── self::atualizarRetornoBitrix()
    │
    ├── (evento: 'deadline', 'cancel', 'auto_close')
    │   └── self::documentoFechado()
    │       └── self::atualizarRetornoBitrix()
    │
    └── (evento: 'document_closed')
        └── self::documentoDisponivel()
            ├── self::atualizarRetornoBitrix()
            ├── self::limparCamposBitrix()
            └── self::moverEtapaBitrix()
```

---

### 3. Fluxo: `atualizarDocumentoClickSign()`

Executa ações de cancelamento ou atualização de data em um documento existente.

```
ClickSignController::atualizarDocumentoClickSign()
├── (action: 'Cancelar Documento')
│   └── ClickSignService::cancelarDocumento()
│       ├── self::getAuthAndDocumentKey()
│       └── self::atualizarRetornoBitrix()
│
└── (action: 'Atualizar Documento')
    └── ClickSignService::atualizarDataDocumento()
        ├── self::getAuthAndDocumentKey()
        └── self::atualizarRetornoBitrix()
```

---

### 4. Fluxo: `extendDeadlineForDueDocuments()` (Job)

Executa a tarefa agendada para adiar o prazo de documentos prestes a vencer.

```
ClickSignController::extendDeadlineForDueDocuments()
└── ClickSignService::processarAdiamentoDePrazos()

---

# Proposta de Refatoração Futura (Nomes em Português)

Abaixo está o plano de como o `ClickSignService` será dividido em 5 arquivos com nomes mais amigáveis em português.

## Detalhamento dos Novos Serviços

```
┌──────────────────────────────────────────────┐
│ GerarAssinaturaService.php                   │
├──────────────────────────────────────────────┤
│ + gerarAssinatura()                          │
│ - validarCamposEssenciais()                   │
│ - processarArquivo()                         │
│ - registrarAssinaturaComRetry()              │
│ - atualizarCamposSignatariosBitrix()         │
└─────────────────────┬────────────────────────┘
                      │
┌─────────────────────▼────────────────────────┐
│ RetornoClickSignService.php                  │
├──────────────────────────────────────────────┤
│ + processarWebhook()                         │
│ - assinaturaRealizada()                      │
│ - documentoFechado()                         │
│ - documentoDisponivel()                      │
└─────────────────────┬────────────────────────┘
                      │
┌─────────────────────▼────────────────────────┐
│ DocumentoService.php (para Atualizações)     │
├──────────────────────────────────────────────┤
│ + cancelarDocumento()                        │
│ + atualizarDataDocumento()                   │
│ - getAuthAndDocumentKey()                    │
└─────────────────────┬────────────────────────┘
                      │
┌─────────────────────▼────────────────────────┐
│ PrazoService.php (Job de Controle de Data)   │
├──────────────────────────────────────────────┤
│ + processarAdiamentoDePrazos()               │
└─────────────────────┬────────────────────────┘
                      │
                      │ (Todos usam o UtilService)
                      ▼
┌──────────────────────────────────────────────┐
│ UtilService.php (Funções em Comum / Úteis)   │
├──────────────────────────────────────────────┤
│ + processarSignatarios()                     │
│ + vincularSignatarios()                      │
│ + atualizarRetornoBitrix()                   │
│ + limparCamposBitrix()                       │
│ + moverEtapaBitrix()                         │
└──────────────────────────────────────────────┘
```
