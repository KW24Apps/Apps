# Padrões de Mensagens e Retornos ClickSign

## 1. Tabela `assinaturas_clicksign`

### Coluna `dados_conexao` (JSON)
- **Uso:** Fonte de verdade para *todas* as operações de um documento ClickSign *após* a criação inicial.
- **Conteúdo:**
  - `webhook_bitrix`: URL para webhooks da ClickSign.
  - `clicksign_token`, `clicksign_secret`: Credenciais ClickSign.
  - `campos`: Mapeamento de campos lógicos para IDs Bitrix24 (ex: `"retorno": "UF_CRM_41_1727805418"`).
    - Inclui: `data`, `retorno`, `contratada`, `contratante`, `idclicksign`, `testemunhas`, `EtapaConcluido`, `arquivoassinado`, `arquivoaserassinado`, `signatarios_assinar`, `signatarios_assinaram`, `acaoclicksign` (opcional).
  - `deal_id`: ID do negócio Bitrix24.
  - `spa`: ID do SPA Bitrix24.
  - `etapa_concluida`: Etapa Bitrix24 ao concluir assinatura.
  - `signatarios_detalhes`: Detalhes dos signatários.

## 2. Tabela `cliente_aplicacoes`

### Coluna `config_extra` (JSON)
- **Uso:** Fonte de verdade *apenas* para a *criação inicial* de uma assinatura na `assinaturas_clicksign`.
- **Conteúdo (por SPA):**
  - `SPA_XXXX`: Objeto de configuração para um SPA específico.
    - `campos`: Mapeamento de campos lógicos para IDs Bitrix24.
    - `clicksign_token`, `clicksign_secret`: Credenciais ClickSign.
    - `bitrix_user_id_comments`: ID do usuário Bitrix para comentários.

## Fluxo de Dados e Regras

1.  **Geração de Assinatura (`GerarAssinaturaService`):**
    *   Consulta `cliente_aplicacoes.config_extra` para obter todas as configurações (campos, webhooks, credenciais) para o SPA relevante.
    *   Realiza validação do cliente (ativo, pago).
    *   Cria o registro na `assinaturas_clicksign`, populando `dados_conexao` com as informações do `config_extra`.

2.  **Operações Pós-Criação (Ex: `DocumentoService`, `RetornoClickSignService`):**
    *   **NUNCA** consulta `cliente_aplicacoes.config_extra` para dados relacionados à ClickSign (campos, webhooks, credenciais).
    *   **SEMPRE** consulta `assinaturas_clicksign.dados_conexao` para obter todas as informações necessárias para o documento específico.
    *   `DocumentoService` pode realizar validação de cliente, mas deve então buscar os dados da `assinaturas_clicksign`.

3.  **Uso de Variável Global para `config_extra` / `dados_conexao`:**
    *   Após a consulta inicial (seja de `cliente_aplicacoes.config_extra` para criação ou `assinaturas_clicksign.dados_conexao` para operações pós-criação), os dados relevantes (campos de retorno, arquivos, etc.) devem ser armazenados em uma variável global.
    *   Isso evita a necessidade de reenviar esses dados em cada requisição ou de realizar novas consultas desnecessárias, tornando o processo mais prático e eficiente.

## Objetivo

- Garantir que `assinaturas_clicksign.dados_conexao` seja a única fonte de dados para um documento ClickSign após sua criação.
- Simplificar a lógica de onde buscar as informações, evitando dados errados ou inconsistências.
- Otimizar o acesso aos dados de configuração e retorno através de variáveis globais.
- **Padronizar Mensagens de Retorno:**
    - No campo `retorno` da função ClickSign: A mensagem deve ser formatada como `CODIGO-descricao-slug-like` (ex: `CS001-parametros-ausentes`).
    - Na timeline (Bitrix): A mensagem deve continuar sendo amigável, sem códigos.
