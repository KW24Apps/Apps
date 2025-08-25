<?php

namespace Controllers;

require_once __DIR__ . '/../helpers/BitrixDiskHelper.php';
require_once __DIR__ . '/../helpers/BitrixDealHelper.php';
require_once __DIR__ . '/../helpers/LogHelper.php';

use Helpers\BitrixDiskHelper;
use Helpers\BitrixDealHelper;
use Helpers\LogHelper;

class DiskController
{
    public function RenomearPasta()
    {
        header('Content-Type: application/json');

        // 1. Obter e validar os parâmetros da URL
        $params = $_GET;
        
        $idPastaMae = $params['idpasta'] ?? null; // Agora é o ID da pasta mãe
        $busca = $params['busca'] ?? null;       // Novo parâmetro para busca
        $nomepasta = $params['nomepasta'] ?? null; // Continua sendo o novo nome
        $spa = $params['spa'] ?? null;
        $deal = $params['deal'] ?? null;
        $retorno = $params['retorno'] ?? null;

        if (empty($idPastaMae) || empty($busca) || empty($nomepasta) || empty($spa) || empty($deal) || empty($retorno)) {
            http_response_code(400);
            $response = ['success' => false, 'mensagem' => 'Parâmetros obrigatórios ausentes: idpasta (mãe), busca, nomepasta, spa, deal, retorno.'];
            LogHelper::logDisk("Parâmetros obrigatórios ausentes: " . json_encode($params), __CLASS__ . '::' . __FUNCTION__);
            echo json_encode($response, JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            // 2. Encontrar o ID da pasta alvo
            $idPastaAlvo = BitrixDiskHelper::findSubfolderIdByName($idPastaMae, $busca);

            if (!$idPastaAlvo) {
                throw new \Exception("Nenhuma pasta contendo o trecho '{$busca}' foi encontrada dentro da pasta mãe ID {$idPastaMae}.");
            }

            // 3. Renomear a pasta no Bitrix Disk
            $renameResult = BitrixDiskHelper::renameFolder($idPastaAlvo, $nomepasta);

            if (isset($renameResult['error'])) {
                 throw new \Exception('Erro ao renomear a pasta: ' . ($renameResult['error_description'] ?? 'Erro desconhecido do Bitrix.'));
            }

            // 4. Atualizar o campo no Deal com o código de sucesso
            $fieldsToUpdate = [
                $retorno => 'BD101' // Código para "Nome de pasta atualizado"
            ];
            
            $updateResult = BitrixDealHelper::editarDeal($spa, $deal, $fieldsToUpdate);

            if ($updateResult['status'] !== 'sucesso') {
                throw new \Exception('Erro ao atualizar o deal: ' . $updateResult['mensagem']);
            }

            // 5. Retornar sucesso
            http_response_code(200);
            echo json_encode([
                'status' => 'sucesso',
                'mensagem' => "Pasta ID {$idPastaAlvo} renomeada e deal atualizado com sucesso.",
                'resultado_rename' => $renameResult,
                'resultado_update' => $updateResult
            ]);

        } catch (\Exception $e) {
            http_response_code(500);
            LogHelper::logDisk("Exceção capturada: " . $e->getMessage(), __CLASS__ . '::' . __FUNCTION__);
            echo json_encode(['erro' => $e->getMessage()]);
        }
    }
}
