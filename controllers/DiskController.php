<?php

namespace Controllers;

require_once __DIR__ . '/../helpers/BitrixDiskHelper.php';
require_once __DIR__ . '/../helpers/BitrixCompanyHelper.php';
require_once __DIR__ . '/../helpers/LogHelper.php';

use Helpers\BitrixDiskHelper;
use Helpers\BitrixCompanyHelper;
use Helpers\LogHelper;

class DiskController
{
    public function RenomearPasta()
    {
        header('Content-Type: application/json');

        // IDs dos campos customizados
        $fieldIdDominioAntigo = 'UF_CRM_1756209754';
        $fieldIdDominioAtual = 'UF_CRM_1656592471';
        $fieldNomeEmpresaAntigo = 'UF_CRM_1756209679';
        $fieldRetorno = 'UF_CRM_1750450438';

        try {
            // 1. Obter e validar os parâmetros da URL
            $params = $_REQUEST;
            $idPastaMae = $params['idpasta'] ?? null;
            $companyid = $params['companyid'] ?? null;

            if (empty($idPastaMae) || empty($companyid)) {
                throw new \InvalidArgumentException('Parâmetros obrigatórios ausentes: idpasta e companyid.');
            }

            // 2. Consultar a empresa para obter os dados necessários
            $companyData = BitrixCompanyHelper::consultarEmpresa(['empresa' => $companyid]);
            if (empty($companyData) || isset($companyData['erro'])) {
                throw new \Exception("Empresa com ID {$companyid} não encontrada ou erro ao consultar.");
            }

            // 3. Extrair informações da empresa
            $busca = $companyData[$fieldIdDominioAntigo] ?? null;
            $idDominioAtual = $companyData[$fieldIdDominioAtual] ?? null;
            $nomePadraoEmpresa = $companyData['TITLE'] ?? null;

            if (empty($busca) || empty($idDominioAtual) || empty($nomePadraoEmpresa)) {
                throw new \Exception("Campos essenciais (ID Domínio Antigo, ID Domínio Atual, Nome) não encontrados na empresa ID {$companyid}.");
            }

            // 4. Construir o novo nome da pasta
            $novoNomePasta = $idDominioAtual . ' - ' . $nomePadraoEmpresa;

            // 5. Encontrar o ID da pasta alvo
            $idPastaAlvo = BitrixDiskHelper::findSubfolderIdByName($idPastaMae, $busca);
            if (!$idPastaAlvo) {
                throw new \Exception("Nenhuma pasta contendo o trecho '{$busca}' foi encontrada dentro da pasta mãe ID {$idPastaMae}.");
            }

            // 6. Renomear a pasta no Bitrix Disk
            $renameResult = BitrixDiskHelper::renameFolder($idPastaAlvo, $novoNomePasta);
            if (isset($renameResult['error']) || empty($renameResult['result'])) {
                throw new \Exception('Erro ao renomear a pasta: ' . ($renameResult['error_description'] ?? 'Resposta inválida da API.'));
            }

            // 7. Extrair o link da pasta e atualizar os campos na Company
            $linkPasta = $renameResult['result']['DETAIL_URL'] ?? null;
            $fieldLinkPasta = 'UF_CRM_1660100679';

            $companyUpdateData = [
                'id' => $companyid,
                $fieldNomeEmpresaAntigo => $nomePadraoEmpresa, // Atualiza o nome antigo com o nome atual
                $fieldIdDominioAntigo => $idDominioAtual,   // Atualiza o ID antigo com o ID atual
                $fieldLinkPasta => $linkPasta,               // Adiciona o link da pasta
                $fieldRetorno => 'BD101'                     // Seta o código de retorno
            ];
            $updateResult = BitrixCompanyHelper::editarCamposEmpresa($companyUpdateData);
            if (isset($updateResult['error'])) {
                throw new \Exception('Erro ao atualizar a company: ' . ($updateResult['error_description'] ?? 'Erro desconhecido.'));
            }

            // 8. Retornar sucesso
            http_response_code(200);
            echo json_encode([
                'status' => 'sucesso',
                'mensagem' => "Pasta ID {$idPastaAlvo} renomeada para '{$novoNomePasta}' e Company ID {$companyid} atualizada.",
                'resultado_rename' => $renameResult,
                'resultado_update' => $updateResult
            ]);

        } catch (\InvalidArgumentException $e) {
            http_response_code(400); // Bad Request for missing params
            LogHelper::logDisk("Erro de parâmetro: " . $e->getMessage(), __CLASS__ . '::' . __FUNCTION__);
            echo json_encode(['erro' => $e->getMessage()]);
        } catch (\Exception $e) {
            http_response_code(500); // Internal Server Error for other issues
            LogHelper::logDisk("Exceção capturada: " . $e->getMessage(), __CLASS__ . '::' . __FUNCTION__);
            echo json_encode(['erro' => $e->getMessage()]);
        }
    }
}
