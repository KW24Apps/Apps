<?php

require_once __DIR__ . '/../helpers/BitrixHelper.php';
require_once __DIR__ . '/../DAO/BitrixSyncDAO.php';

class BitrixSyncController {

    private $tokenBitrix = 'jopxai49pn9vfu4zre0ce1jm0xvhuxww';
    private $webhookFixo = 'https://gnapp.bitrix24.com.br/rest/21/p5o68gau1pdoe2z0/';

    public function executar() {
        header('Content-Type: application/json');

        $input = json_decode(file_get_contents('php://input'), true);

        if (!isset($input['application_token']) || $input['application_token'] !== $this->tokenBitrix) {
            http_response_code(403);
            echo json_encode(['erro' => 'Token inválido']);
            return;
        }

        if (!isset($input['empresa_id']) || !is_numeric($input['empresa_id'])) {
            http_response_code(400);
            echo json_encode(['erro' => 'ID da empresa inválido']);
            return;
        }

        $idEmpresa = (int) $input['empresa_id'];

        // Campos desejados da empresa
        $camposEmpresa = [
            'ID', 'TITLE', 'UF_CRM_1692234818', 'UF_CRM_1656537733', 'UF_CRM_1748894313',
            'PHONE', 'EMAIL', 'ADDRESS',
            'UF_CRM_1684436968', 'UF_CRM_1695306238', 'UF_CRM_1733848071',
            'UF_CRM_1748893926', 'UF_CRM_1748894560',
            'UF_CRM_1748893996', 'UF_CRM_1748894312',
            'UF_CRM_1748893997', 'UF_CRM_1748894313',
            'UF_CRM_1748893998', 'UF_CRM_1748894314',
            'UF_CRM_1748894247', 'UF_CRM_1748894311'
        ];

        // Buscar dados da empresa (dados crus)
        $empresa = BitrixHelper::getEmpresaById($idEmpresa, $this->webhookFixo, $camposEmpresa);
        if (!$empresa) {
            http_response_code(404);
            echo json_encode(['erro' => 'Empresa não encontrada no Bitrix']);
            return;
        }

        // Extrair todos os IDs de contatos vinculados
        $idsContatos = array_merge(
            $empresa['UF_CRM_1684436968'] ?? [],
            $empresa['UF_CRM_1695306238'] ?? [],
            $empresa['UF_CRM_1733848071'] ?? []
        );

        $contatos = [];
        $camposContato = ['ID', 'NAME', 'LAST_NAME', 'PHONE', 'EMAIL', 'UF_CRM_1670006133'];

        foreach ($idsContatos as $idContato) {
            $contato = BitrixHelper::getContatoById($idContato, $this->webhookFixo, $camposContato);
            if ($contato) {
                $contatos[] = $contato;
            }
        }

        echo json_encode(['status' => 'ok', 'empresa' => $empresa, 'contatos' => $contatos]);
    }
}
