<?php
namespace Enums;

class ClickSignCodes
{
    // CS0XX - Validações de Entrada
    const PARAMS_AUSENTES = 'CS001';
    const ACESSO_NAO_AUTORIZADO = 'CS002';
    const DEAL_NAO_ENCONTRADO = 'CS003';
    const ARQUIVO_OBRIGATORIO = 'CS004';
    const DATA_LIMITE_OBRIGATORIA = 'CS005';
    const DATA_LIMITE_PASSADO = 'CS006';
    const SIGNATARIO_OBRIGATORIO = 'CS007';
    const CAMPOS_INVALIDOS = 'CS008';

    // CS1XX - Validações de Signatários
    const DADOS_SIGNATARIO_FALTANTES = 'CS101';
    const DADOS_SIGNATARIO_INCOMPLETOS = 'CS102';
    const DADOS_SIGNATARIO_NAO_ENCONTRADOS_EVENTO = 'CS103';

    // CS2XX - Processamento de Arquivos
    const ERRO_CONVERTER_ARQUIVO = 'CS201';
    const FALHA_CONVERTER_ARQUIVO = 'CS202';
    const ERRO_BAIXAR_ARQUIVO_ANEXO = 'CS203';

    // CS3XX - Operações ClickSign
    const FALHA_CRIAR_SIGNATARIO = 'CS301';
    const FALHA_VINCULAR_SIGNATARIO = 'CS302';
    const FALHA_VINCULO_SIGNATARIOS = 'CS303';

    // CS4XX - Sucessos Principais
    const DOCUMENTO_ENVIADO = 'CS401';
    const ASSINATURA_REALIZADA = 'CS402';
    const DOCUMENTO_ASSINADO = 'CS403';
    const ARQUIVO_ENVIADO_BITRIX = 'CS404';
    const PROCESSO_FINALIZADO_COM_ANEXO = 'CS405';

    // CS5XX - Estados de Documento e Jobs
    const PRAZO_ESTENDIDO_AUTO = 'CS501'; // Nosso código customizado
    const ASSINATURA_CANCELADA_PRAZO = 'CS502';
    const ASSINATURA_CANCELADA_MANUAL = 'CS503';
    const EVENTO_AUTO_CLOSE_SALVO = 'CS504';
    const PROCESSO_FINALIZADO_SEM_ANEXO = 'CS505';

    // CS6XX - Controle de Duplicatas
    const ASSINATURA_JA_PROCESSADA = 'CS601';
    const EVENTO_FECHADO_JA_PROCESSADO = 'CS602';
    const DOCUMENTO_JA_DISPONIVEL = 'CS603';
    const ASSINATURA_JA_EM_ANDAMENTO = 'CS604';

    // CS7XX - Webhooks e Retornos
    const WEBHOOK_PARAMS_AUSENTES = 'CS701';
    const HMAC_INVALIDO = 'CS702';
    const DOCUMENTO_NAO_ENCONTRADO_BD = 'CS703';
    const CREDENCIAIS_API_NAO_CONFIGURADAS = 'CS704';
    const EVENTO_SEM_ACAO = 'CS705';

    // CS9XX - Erros de Controle Interno
    const FALHA_GRAVAR_ASSINATURA_BD = 'CS901';
    const TOKEN_AUSENTE = 'CS904';
}
