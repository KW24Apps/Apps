<?php
require_once __DIR__ . '/../helpers/BitrixHelper.php';
require_once __DIR__ . '/../dao/BitrixSincDao.php';
class BitrixSyncController
{
    private $bitrixHelper;
    private $dao;
    private $logFile;

    public function __construct()
    {
        $this->bitrixHelper = new BitrixHelper();
        $this->dao = new BitrixSincDAO();
        $this->logFile = __DIR__ . '/../logs/bitrix_sync.log';
    }

    public function syncCompany()
    {
        try {
            if (!isset($_GET['company_id'])) {
                $this->log('Erro: company_id não informado.');
                http_response_code(400);
                echo json_encode(['error' => 'company_id is required']);
                return;
            }

            $companyId = $_GET['company_id'];
            $this->log("Iniciando sincronização para company_id: $companyId");
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

           $resultadoEmpresas = $this->bitrixHelper->consultarEmpresas(['bitrix' => [$companyId]], $webhookPadrao, $camposEmpresa);

            $company = $resultadoEmpresas['bitrix'][0] ?? null;
            
            if (!$company) {
                $this->log("Empresa ID $companyId não encontrada no Bitrix.");
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
            $this->log("Empresa extraída: " . json_encode($empresa));

            $empresaDb = $this->dao->buscarEmpresaPorIdBitrix($empresa['id_bitrix']);
            if ($empresaDb) {
                $this->dao->atualizarEmpresa($empresa);
                $empresa['id'] = $empresaDb['id'];
                $this->log("Empresa atualizada no banco: ID local {$empresa['id']}");
            } else {
                $empresa['id'] = $this->dao->inserirEmpresa($empresa);
                $this->log("Empresa inserida no banco: ID local {$empresa['id']}");
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

                $resultadoContatos = $this->bitrixHelper->consultarContatos(
                    ['bitrix' => $idsContatos],
                    $webhookPadrao,
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
                    $this->log("Contato sincronizado: " . json_encode($dadosContato));
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
                $valorCampo = strtolower(trim((string) ($company[$campos['ativo']] ?? '')));
                $ativo = $valorCampo === 'true' ? 1 : 0;
                $webhook = $company[$campos['webhook']] ?? null;
                $this->dao->sincronizarAplicacao($empresa['id'], $aplicacaoId, $ativo, $webhook);
                $this->log("Aplicação ID $aplicacaoId sincronizada. Ativo: $ativo, Webhook: $webhook");
            }


            $this->log("Sincronização concluída para company_id: $companyId");
            echo json_encode(['status' => 'sincronizacao concluida']);

        } catch (Throwable $e) {
            $erro = '[Erro syncCompany] ' . $e->getMessage() . ' - Linha: ' . $e->getLine() . PHP_EOL;
            file_put_contents(__DIR__ . '/../logs/erros.log', $erro, FILE_APPEND);
            http_response_code(500);
            echo 'Erro interno: ' . $e->getMessage();
        }
    }

    private static function extrairLinkBitrix($company)
    {
        return isset($company['ID']) ? "https://" . $_SERVER['HTTP_HOST'] : null;
    }

    private function log($mensagem)
    {
        $data = date('Y-m-d H:i:s');
        file_put_contents($this->logFile, "[$data] $mensagem\n", FILE_APPEND);
    }
}
