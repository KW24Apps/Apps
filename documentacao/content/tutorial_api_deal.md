# Tutorial de Integração da API de Deals (KW24)

Este documento descreve como realizar integrações com os endpoints de Deals da API KW24, suportando o envio de dados via parâmetros de URL (formato legado) ou através do corpo da requisição em formato JSON (formato recomendado).

---

## 📌 Visão Geral

A API permite **criar**, **editar** e **consultar** Deals utilizando o nome técnico do campo ou seu respectivo nome amigável (ex: usar `Observações` ao invés de `UF_CRM_1773766344`).

A API aceita os parâmetros de duas formas complementares:
1. **Via URL (Query Params)**
2. **Via Corpo JSON (Payload Base)**

Você pode mesclar os dois, preferencialmente enviando a chave do cliente pela URL e os dados do Deal via JSON.

---

## 🛠 Entendendo os Campos

Existem dois tipos principais de campos que a API espera:

### 1. Parâmetros de Controle (URL ou JSON)
Estes são os campos responsáveis por identificar a instância e direcionar o registro.
*   `cliente` (Obrigatório na URL): A chave (token) de acesso da sua instância Bitrix. Exemplo: `triocardmP...`
*   `spa` (Obrigatório): O ID da entidade/pipeline de destino. Para o CRM padrão, `2` representa a entidade clássica de Negócios (Deals).
*   `CATEGORY_ID` (Obrigatório apenas para *Criar*): O ID do funil específico dentro daquele pipeline onde o Deal deve cair inicialmente. Não é necessário informá--lo ao editar ou consultar.
*   `id` (Obrigatório apenas para *Consultar/Editar*): O ID do Deal existente no Bitrix.

### 2. Parâmetros de Dados do Deal (Recomendado via JSON)
Estes são os valores a serem preenchidos na ficha do Deal. Uma grande vantagem da API é a flexibilidade: você pode mandar os dados usando **qualquer um** dos formatos abaixo (ela faz a conversão automática):
*   **Nome Amigável (Recomendado):** `Observações`, `CNPJ/CPF #`, `Telefone Empresa`, `E-mail da empresa`, etc. Em caso de uso dos Nomes Amigáveis, certifique-se de usar o nome exato conforme exibido no seu Bitrix.
*   **ID Técnico do Bitrix:** `ufCrm_1773766344` ou `UF_CRM_1773766344`.

> **⚠️ Atenção ao uso de Nomes Amigáveis:**
> - **Nomes Duplicados:** O Bitrix permite que você crie dois campos diferentes com o mesmo nome (ex: dois campos chamados "Observações"). A API não tem como adivinhar qual deles você quer preencher e usará o primeiro que encontrar, o que pode causar erros nos dados. Certifique-se de que os nomes dos seus campos no Bitrix sejam únicos.
> - **Associação com o SPA:** Os nomes dos campos devem pertencer ao `spa` que você informou. Se você estiver enviando dados para o SPA `2` (Negócios), os campos informados devem existir no cadastro de Negócios. Tentar enviar um campo exclusivo de outro SPA resultará em erro.

---

## 🚀 1. Criar um Deal (`/deal/criar`)

### A) Exemplo Híbrido Recomendado (Autenticação na URL + Dados no JSON)

Endpoint: `POST https://apis.kw24.com.br/deal/criar?cliente=SuaChaveDeClienteSecretaAqui`

**Corpo (JSON RAW):**
```json
{
  "spa": 2,
  "CATEGORY_ID": 0,
  "Observações": "Deal criado através de payload JSON. Tudo funcionando bem!",
  "CNPJ/CPF #": "12.345.678/0001-99",
  "Telefone Empresa": "(11) 98765-4321",
  "E-mail da empresa": "contato@novaempresa.com",
  "Número de colaboradores": 150,
  "Quantidade de veículos": 42,
  "Nome da Empresa": "Nova Empresa LTDA",
  "Nome": "João Silva (Contato Principal)"
}
```

### B) Exemplo Legado (Apenas URL)
Se, por alguma limitação do seu software disparador, for necessário enviar tudo na URL, a API continua aceitando normalmente.
Lembre-se que alguns caracteres especiais (como `#` no nome do campo, ou espaços) vão aparecer em formato URL-encoded (ex: `%23`, `%20`).

Endpoint:
```text
POST https://apis.kw24.com.br/deal/criar?cliente=SuaChave&spa=2&CATEGORY_ID=0&Observações=OBS%20teste&CNPJ/CPF%20%23=123456&Nome%20da%20Empresa=Empresa%20Teste
```

---

## 📝 2. Editar um Deal (`/deal/editar`)
O processo de edição é idêntico ao de criação. A única diferença é a necessidade do parâmetro `id` indicando o registro que receberá os updates.

### Exemplo Recomendado (Autenticação na URL + Updates no JSON)

Endpoint: `POST https://apis.kw24.com.br/deal/editar?cliente=SuaChaveDeClienteSecretaAqui`

**Corpo (JSON RAW):**
```json
{
  "spa": 2,
  "id": 7656,
  "Observações": "Anotação atualizada via integração JSON",
  "Quantidade de veículos": 15
}
```
*(Apenas os campos enviados serão sobrescritos no Bitrix)*

### Exemplo Legado (Apenas URL)

Endpoint:
```text
POST https://apis.kw24.com.br/deal/editar?cliente=SuaChave&spa=2&id=7656&Observações=Anotação%20atualizada%20via%20integração&Quantidade%20de%20veículos=15
```

---

## 🔍 3. Consultar um Deal (`/deal/consultar`)
A consulta pode receber parâmetros de controle para buscar dados de um negócio, bem como uma lista restrita de `campos` se você quiser filtrar a resposta devolvida pela API.

### Exemplo Recomendado (Autenticação na URL + Filtros no JSON)

Endpoint: `GET` ou `POST`  `https://apis.kw24.com.br/deal/consultar?cliente=SuaChaveDeClienteSecretaAqui`

**Corpo (JSON RAW):**
```json
{
  "spa": 2,
  "id": 7656,
  "campos": "Observações, CNPJ/CPF #, Telefone Empresa"
}
```

### Exemplo Legado (Apenas URL)

Endpoint:
```text
GET https://apis.kw24.com.br/deal/consultar?cliente=SuaChave&spa=2&id=7656&campos=Observações,%20CNPJ/CPF%20%23,%20Telefone%20Empresa
```

A API então buscará o negócio 7656 e retornará apenas as `Observações`, o `CNPJ/CPF #` e o `Telefone Empresa` mapeados com seus respectivos nomes e valores amigáveis na propriedade `result`.

---
