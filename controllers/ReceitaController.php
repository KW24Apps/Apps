<?php
namespace Controllers;

require_once __DIR__ . '/../helpers/ReceitaHelper.php';
require_once __DIR__ . '/../dao/BitrixSincDao.php';
require_once __DIR__ . '/../helpers/BitrixCompanyHelper.php';
require_once __DIR__ . '/../helpers/LogHelper.php';

use Helpers\ReceitaHelper;
use dao\BitrixSincDAO;
use Helpers\BitrixCompanyHelper;
use Helpers\LogHelper;

class ReceitaController
{
    public function consultarCNPJWebhook()
    {
        header('Content-Type: application/json');
        $input = json_decode(file_get_contents('php://input'), true);
        $cnpj = $input['cnpj'] ?? null;
        $id_bitrix = $input['id_bitrix'] ?? $input['id_empresa'] ?? null;
        $retorno_api = '';
        if (!$cnpj) {
            // Tenta pegar da query string
            $cnpj = $_GET['cnpj'] ?? null;
        }
        if (!$id_bitrix) {
            $id_bitrix = $_GET['id_bitrix'] ?? $_GET['id_empresa'] ?? $_GET['id'] ?? null;
        }
        if (!$cnpj) {
            http_response_code(400);
            $retorno_api = 'CNPJ invalido';
            echo json_encode(['erro' => 'CNPJ não informado', 'retorno_api' => $retorno_api]);
            return;
        }
        // 1. Limpa o CNPJ e valida
        $cnpjLimpo = preg_replace('/\D/', '', $cnpj);
        if (strlen($cnpjLimpo) !== 14 || !self::validarCNPJ($cnpjLimpo)) {
            http_response_code(400);
            $retorno_api = 'CNPJ invalido';
            echo json_encode([
                'erro' => 'CNPJ inválido',
                'retorno_api' => $retorno_api,
                'mensagem_api' => 'O CNPJ informado foi corrigido ou está inválido. Verifique o valor enviado.'
            ]);
            return;
        }
        // 2. Formata o CNPJ para XX.XXX.XXX/0001-XX
        $cnpjFormatado = substr($cnpjLimpo, 0, 2) . '.' . substr($cnpjLimpo, 2, 3) . '.' . substr($cnpjLimpo, 5, 3) . '/' . substr($cnpjLimpo, 8, 4) . '-' . substr($cnpjLimpo, 12, 2);
        $dadosReceita = ReceitaHelper::consultarCNPJ($cnpjLimpo);
        if (isset($dadosReceita['erro'])) {
            http_response_code(400);
            $retorno_api = 'Erro na API receita';
            echo json_encode(['erro' => $dadosReceita['erro'], 'retorno_api' => $retorno_api]);
            return;
        }
        $retorno_api = 'Dados da receita preenchidos';
        $dao = new BitrixSincDAO();
        $empresaDb = $id_bitrix ? $dao->buscarEmpresaPorIdBitrix($id_bitrix) : null;
        $empresa = $empresaDb ? $empresaDb : [];
        $empresa['nome'] = $dadosReceita['nome'] ?? $dadosReceita['fantasia'] ?? ($empresa['nome'] ?? '');
        $empresa['fantasia'] = $dadosReceita['fantasia'] ?? ($empresa['fantasia'] ?? '');
        $empresa['cnpj'] = $cnpjFormatado;
        $empresa['abertura'] = $dadosReceita['abertura'] ?? ($empresa['abertura'] ?? '');
        $empresa['situacao'] = $dadosReceita['situacao'] ?? ($empresa['situacao'] ?? '');
        $empresa['porte'] = $dadosReceita['porte'] ?? ($empresa['porte'] ?? '');
        $empresa['natureza_juridica'] = $dadosReceita['natureza_juridica'] ?? ($empresa['natureza_juridica'] ?? '');
        $empresa['atividade_principal'] = isset($dadosReceita['atividade_principal'][0]['text']) ? $dadosReceita['atividade_principal'][0]['text'] : ($empresa['atividade_principal'] ?? '');
        $empresa['codigo_atividade_principal'] = isset($dadosReceita['atividade_principal'][0]['code']) ? $dadosReceita['atividade_principal'][0]['code'] : ($empresa['codigo_atividade_principal'] ?? '');
        $empresa['atividades_secundarias'] = isset($dadosReceita['atividades_secundarias']) ? json_encode($dadosReceita['atividades_secundarias']) : ($empresa['atividades_secundarias'] ?? '');
        $empresa['capital_social'] = $dadosReceita['capital_social'] ?? ($empresa['capital_social'] ?? '');
        $empresa['simples'] = isset($dadosReceita['simples']) ? json_encode($dadosReceita['simples']) : ($empresa['simples'] ?? '');
        $empresa['simei'] = isset($dadosReceita['simei']) ? json_encode($dadosReceita['simei']) : ($empresa['simei'] ?? '');
        $empresa['data_situacao'] = $dadosReceita['data_situacao'] ?? ($empresa['data_situacao'] ?? '');
        $empresa['tipo'] = $dadosReceita['tipo'] ?? ($empresa['tipo'] ?? '');
        $empresa['cep'] = $dadosReceita['cep'] ?? ($empresa['cep'] ?? '');
        $empresa['municipio'] = $dadosReceita['municipio'] ?? ($empresa['municipio'] ?? '');
        $empresa['uf'] = $dadosReceita['uf'] ?? ($empresa['uf'] ?? '');
        $empresa['telefone'] = $dadosReceita['telefone'] ?? ($empresa['telefone'] ?? '');
        $empresa['email'] = $dadosReceita['email'] ?? ($empresa['email'] ?? '');
        $empresa['logradouro'] = $dadosReceita['logradouro'] ?? ($empresa['logradouro'] ?? '');
        $empresa['numero'] = $dadosReceita['numero'] ?? ($empresa['numero'] ?? '');
        $empresa['bairro'] = $dadosReceita['bairro'] ?? ($empresa['bairro'] ?? '');
        $empresa['complemento'] = $dadosReceita['complemento'] ?? ($empresa['complemento'] ?? '');
        $empresa['endereco'] = trim(($dadosReceita['logradouro'] ?? '') . ' ' . ($dadosReceita['numero'] ?? '') . ' ' . ($dadosReceita['bairro'] ?? '') . ' ' . ($dadosReceita['municipio'] ?? '') . ' ' . ($dadosReceita['uf'] ?? ''));
        $empresa['id_bitrix'] = $id_bitrix;
        if ($id_bitrix) {
            if ($empresaDb) {
                $dao->atualizarEmpresa($empresa);
            } else {
                $dao->inserirEmpresa($empresa);
            }
            $camposBitrix = [
                'UF_CRM_1643894689490' => $empresa['fantasia'], // nome fantasia
                'UF_CRM_1742233369' => $empresa['nome'], // nome empresa
                // 'UF_CRM_1641693445101' => $empresa['cnpj'],
                // 'TITLE' => $empresa['nome'],
                // 'UF_CRM_ABERTURA' => $empresa['abertura'],
                // 'UF_CRM_SITUACAO' => $empresa['situacao'],
                // 'UF_CRM_PORTE' => $empresa['porte'],
                // 'UF_CRM_NATUREZA_JURIDICA' => $empresa['natureza_juridica'],
                // 'UF_CRM_ATIVIDADE_PRINCIPAL' => $empresa['atividade_principal'],
                // 'UF_CRM_COD_ATIVIDADE_PRINCIPAL' => $empresa['codigo_atividade_principal'],
                // 'UF_CRM_ATIVIDADES_SECUNDARIAS' => $empresa['atividades_secundarias'],
                // 'UF_CRM_CAPITAL_SOCIAL' => $empresa['capital_social'],
                // 'UF_CRM_SIMPLES' => $empresa['simples'],
                // 'UF_CRM_SIMEI' => $empresa['simei'],
                // 'UF_CRM_DATA_SITUACAO' => $empresa['data_situacao'],
                // 'UF_CRM_TIPO' => $empresa['tipo'],
                // 'UF_CRM_CEP' => $empresa['cep'],
                // 'UF_CRM_MUNICIPIO' => $empresa['municipio'],
                // 'UF_CRM_UF' => $empresa['uf'],
                // 'EMAIL' => $empresa['email'],
                // 'PHONE' => $empresa['telefone'],
                // 'UF_CRM_LOGRADOURO' => $empresa['logradouro'],
                // 'UF_CRM_NUMERO' => $empresa['numero'],
                // 'UF_CRM_BAIRRO' => $empresa['bairro'],
                // 'UF_CRM_COMPLEMENTO' => $empresa['complemento'],
                // 'ADDRESS' => $empresa['endereco']
            ];
            $camposBitrix['id'] = $id_bitrix;
            BitrixCompanyHelper::editarCamposEmpresa($camposBitrix);
        } else {
            $dao->inserirEmpresa($empresa);
        }
        LogHelper::registrarEntradaGlobal('receita', 'POST');
        echo json_encode([
            'dados_receita' => $dadosReceita,
            'empresa_atualizada' => $empresa,
            'retorno_api' => $retorno_api,
            'UF_CRM_1753465280' => $retorno_api // campo de retorno api customizado
        ]);
    }
    // Função para validar CNPJ (padrão Receita Federal)
    private static function validarCNPJ($cnpj)
    {
        if (preg_match('/(\d)\1{13}/', $cnpj)) return false;
        $tamanho = strlen($cnpj) - 2;
        $numeros = substr($cnpj, 0, $tamanho);
        $digitos = substr($cnpj, $tamanho);
        $soma = 0;
        $pos = $tamanho - 7;
        for ($i = $tamanho; $i >= 1; $i--) {
            $soma += $numeros[$tamanho - $i] * $pos--;
            if ($pos < 2) $pos = 9;
        }
        $resultado = ($soma % 11 < 2) ? 0 : 11 - ($soma % 11);
        if ($resultado != $digitos[0]) return false;
        $tamanho++;
        $numeros = substr($cnpj, 0, $tamanho);
        $soma = 0;
        $pos = $tamanho - 7;
        for ($i = $tamanho; $i >= 1; $i--) {
            $soma += $numeros[$tamanho - $i] * $pos--;
            if ($pos < 2) $pos = 9;
        }
        $resultado = ($soma % 11 < 2) ? 0 : 11 - ($soma % 11);
        return $resultado == $digitos[1];
    }
}
