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
    const FALHA_CANCELAR_DOCUMENTO = 'CS304';
    const FALHA_ATUALIZAR_DOCUMENTO = 'CS305';

    // CS4XX - Sucessos Principais
    const DOCUMENTO_ENVIADO = 'CS401';
    const ASSINATURA_REALIZADA = 'CS402';
    const PROCESSO_FINALIZADO_COM_ANEXO = 'CS405';

    // CS5XX - Estados de Documento e Jobs
    const PRAZO_ESTENDIDO_AUTO = 'CS501'; // Nosso código customizado
    const ASSINATURA_CANCELADA_PRAZO = 'CS502';
    const ASSINATURA_CANCELADA_MANUAL = 'CS503';
    const PROCESSO_FINALIZADO_SEM_ANEXO = 'CS505';
    const DATA_ATUALIZADA_MANUALMENTE = 'CS506';
    const FALHA_ADIAR_PRAZO = 'CS507';
    const EXCECAO_PROCESSAMENTO_PRAZO = 'CS508';

    // CS6XX - Controle de Duplicatas
    const ASSINATURA_JA_EM_ANDAMENTO = 'CS604';

    // CS7XX - Webhooks e Retornos
    const WEBHOOK_PARAMS_AUSENTES = 'CS701';
    const HMAC_INVALIDO = 'CS702';
    const DOCUMENTO_NAO_ENCONTRADO_BD = 'CS703';
    const CREDENCIAIS_API_NAO_CONFIGURADAS = 'CS704';

    // CS9XX - Erros de Controle Interno
    const FALHA_GRAVAR_ASSINATURA_BD = 'CS901';
    const TOKEN_AUSENTE = 'CS904';
}
