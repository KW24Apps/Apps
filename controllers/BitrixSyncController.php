<?php

require_once __DIR__ . '/../helpers/BitrixHelper.php';
require_once __DIR__ . '/../DAO/BitrixSyncDAO.php';

class BitrixSyncController {

    private $tokenBitrix = 'jopxai49pn9vfu4zre0ce1jm0xvhuxww';
    private $webhookFixo = 'https://gnapp.bitrix24.com.br/rest/21/p5o68gau1pdoe2z0/';

    public function executar() {
        header('Content-Type: application/json');

        // Captura o corpo JSON enviado pelo Bitrix
        $input = json_decode(file_get_contents('php://input'), true);

        // Validação do token
        if (!isset($input['application_token']) || $input['application_token'] !== $this->tokenBitrix) {
            http_response_code(403);
            echo json_encode(['erro' => 'Token inválido']);
            return;
        }

        // Validação do ID da empresa
        if (!isset($input['empresa_id']) || !is_numeric($input['empresa_id'])) {
            http_response_code(400);
            echo json_encode(['erro' => 'ID da empresa inválido']);
            return;
        }

        $idEmpresa = (int) $input['empresa_id'];

        // Buscar dados da empresa
        $empresa = BitrixHelper::getEmpresaById($idEmpresa, $this->webhookFixo);
        if (!$empresa) {
            http_response_code(404);
            echo json_encode(['erro' => 'Empresa não encontrada no Bitrix']);
            return;
        }

        // Buscar contatos vinculados
        $contatos = [];
        if (!empty($empresa['CONTACT_ID'])) {
            foreach ($empresa['CONTACT_ID'] as $idContato) {
                $contato = BitrixHelper::getContatoById($idContato, $this->webhookFixo);
                if ($contato) {
                    $contatos[] = $contato;
                }
            }
        }

        // Salvar ou atualizar dados no banco
        $dao = new BitrixSyncDAO();
        $clienteId = $dao->salvarOuAtualizarCliente($empresa);
        $dao->sincronizarContatos($clienteId, $contatos);

        echo json_encode(['status' => 'sucesso']);
    }
}
