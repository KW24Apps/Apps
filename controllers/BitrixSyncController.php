<?php
namespace Controllers;

require_once __DIR__ . '/../helpers/BitrixHelper.php';
require_once __DIR__ . '/../dao/BitrixSincDao.php';
require_once __DIR__ . '/../helpers/BitrixCompanyHelper.php';
require_once __DIR__ . '/../helpers/BitrixContactHelper.php';

use Helpers\BitrixHelper;
use dao\BitrixSincDAO;
use Helpers\BitrixCompanyHelper;
use Helpers\BitrixContactHelper;
use Throwable;



class BitrixSyncController
{
    private $dao;

    public function __construct()
    {
        $this->dao = new BitrixSincDAO();
    }

    public function syncCompany()
    {
        try {
            if (!isset($_GET['company_id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'company_id is required']);
                return;
            }

            $companyId = $_GET['company_id'];
                $camposEmpresa = [
                    'ID',
                    'TITLE',
                    'UF_CRM_1641693445101',
                    'UF_CRM_1748955982',
                    'PHONE',
                    'EMAIL',
                    'ADDRESS',
                    'UF_CRM_1684436968',
                    'UF_CRM_1695306238',
                    'UF_CRM_1733848071',
                    'UF_CRM_1748893926',
                    'UF_CRM_1748894560',
                    'UF_CRM_1748893996',
                    'UF_CRM_1748894312',
                    'UF_CRM_1748893997',
                    'UF_CRM_1748894313',
                    'UF_CRM_1748893998',
                    'UF_CRM_1748894314',
                    'UF_CRM_1748894247',
                    'UF_CRM_1748894311'
                ];

            // define webhook padrão
            $webhookPadrao = 'https://gnapp.bitrix24.com.br/rest/21/yzwc932754bgujc3'; // ajuste se necessário

            $resultadoEmpresas = BitrixCompanyHelper::consultarEmpresas(['bitrix' => [$companyId]],$camposEmpresa);


            $company = $resultadoEmpresas['bitrix'][0] ?? null;
            
            if (!$company) {
                http_response_code(404);
                echo json_encode(['error' => 'Empresa não encontrada no Bitrix']);
                return;
            }

            $empresa = [
                'id_bitrix'      => $company['ID'],
                'nome'           => $company['TITLE'],
                'cnpj'           => $company['UF_CRM_1641693445101'] ?? null,
                'chave_acesso'   => $company['UF_CRM_1748955982'] ?? null,
                'telefone'       => $company['PHONE'][0]['VALUE'] ?? null,
                'email'          => $company['EMAIL'][0]['VALUE'] ?? null,
                'endereco'       => $company['ADDRESS'] ?? null,
                'link_bitrix'    => self::extrairLinkBitrix($company)
            ];

            $empresaDb = $this->dao->buscarEmpresaPorIdBitrix($empresa['id_bitrix']);
            if ($empresaDb) {
                $this->dao->atualizarEmpresa($empresa);
                $empresa['id'] = $empresaDb['id'];
            } else {
                $empresa['id'] = $this->dao->inserirEmpresa($empresa);
            }

            $camposContatos = [
                'UF_CRM_1684436968',
                'UF_CRM_1695306238',
                'UF_CRM_1733848071'
            ];

            $idsContatos = [];
            foreach ($camposContatos as $campo) {
                if (!empty($company[$campo])) {
                    foreach ((array)$company[$campo] as $idContato) {
                        $idsContatos[] = $idContato;
                    }
                }
            }

            if (!empty($idsContatos)) {
                $camposContato = [
                    'ID',
                    'NAME',
                    'LAST_NAME',
                    'POST',
                    'PHONE',
                    'EMAIL'
                ];

                $resultadoContatos = BitrixContactHelper::consultarContatos(    
                    ['bitrix' => $idsContatos],
                    $camposContato
);
                foreach ($resultadoContatos['bitrix'] as $contato) {
                    $dadosContato = [
                        'id_bitrix' => $contato['ID'],
                        'nome'      => trim($contato['NAME'] . ' ' . $contato['LAST_NAME']),
                        'cargo'     => $contato['POST'] ?? null,
                        'telefone'  => $contato['PHONE'][0]['VALUE'] ?? null,
                        'email'     => $contato['EMAIL'][0]['VALUE'] ?? null
                    ];
                    $this->dao->sincronizarContato($empresa['id'], $dadosContato);
                }
            }

            $aplicacoes = [
                1 => ['ativo' => 'UF_CRM_1748893926', 'webhook' => 'UF_CRM_1748894560'],
                2 => ['ativo' => 'UF_CRM_1748893996', 'webhook' => 'UF_CRM_1748894312'],
                3 => ['ativo' => 'UF_CRM_1748893997', 'webhook' => 'UF_CRM_1748894313'],
                4 => ['ativo' => 'UF_CRM_1748893998', 'webhook' => 'UF_CRM_1748894314'],
                5 => ['ativo' => 'UF_CRM_1748894247', 'webhook' => 'UF_CRM_1748894311']
            ];

                foreach ($aplicacoes as $aplicacaoId => $campos) {
                    $valorCampoBruto = $company[$campos['ativo']] ?? '';
                    $valorCampo = strtolower(trim((string) $valorCampoBruto));
                    $ativo = in_array($valorCampo, ['1', 'y', 'yes', 'true']) ? 1 : 0;
                    $webhook = $company[$campos['webhook']] ?? null;

                    $this->dao->sincronizarAplicacao($empresa['id'], $aplicacaoId, $ativo, $webhook);
                }


            echo json_encode(['status' => 'sincronizacao concluida']);

        } catch (Throwable $e) {
            $erro = '[Erro syncCompany] ' . $e->getMessage() . ' - Linha: ' . $e->getLine() . PHP_EOL;
            http_response_code(500);
            echo 'Erro interno: ' . $e->getMessage();
        }
    }

    private static function extrairLinkBitrix($company)
    {
        return isset($company['ID']) ? "https://" . $_SERVER['HTTP_HOST'] : null;
    }
}
