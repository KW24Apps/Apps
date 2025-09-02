<?php

namespace Controllers;

require_once __DIR__ . '/../helpers/BitrixDiskHelper.php';
require_once __DIR__ . '/../helpers/BitrixCompanyHelper.php';
require_once __DIR__ . '/../helpers/BitrixHelper.php';
require_once __DIR__ . '/../helpers/LogHelper.php';

use Helpers\BitrixDiskHelper;
use Helpers\BitrixCompanyHelper;
use Helpers\BitrixHelper;
use Helpers\LogHelper;

class DiskController
{
    public function RenomearPasta()
    {
        header('Content-Type: application/json');

        // IDs dos campos customizados
        $fieldIdDominioAntigo = 'ufCrm_1756209754';
        $fieldIdDominioAtual = 'ufCrm_1656592471';
        $fieldNomeEmpresaAntigo = 'ufCrm_1756209679';
        // O campo de retorno foi removido, agora usamos a timeline

        $companyid = null; // Inicializa para o bloco catch

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
            $nomePadraoEmpresa = $companyData['title'] ?? null;

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

            // 7. Atualizar os campos na Company (sem o campo de retorno)
            $companyUpdateData = [
                'id' => $companyid,
                $fieldNomeEmpresaAntigo => $nomePadraoEmpresa,
                $fieldIdDominioAntigo => $idDominioAtual
            ];
            $updateResult = BitrixCompanyHelper::editarCamposEmpresa($companyUpdateData);
            if (isset($updateResult['error'])) {
                throw new \Exception('Erro ao atualizar a company: ' . ($updateResult['error_description'] ?? 'Erro desconhecido.'));
            }

            // 8. Adicionar comentário de sucesso na timeline
            $mensagemSucesso = "Pasta da Contabilidade foi renomeada para: '{$novoNomePasta}'.";
            BitrixHelper::adicionarComentarioTimeline('company', (int)$companyid, $mensagemSucesso);

            // 9. Retornar sucesso
            http_response_code(200);
            echo json_encode([
                'status' => 'sucesso',
                'mensagem' => "Pasta ID {$idPastaAlvo} renomeada e comentário adicionado na Company ID {$companyid}.",
                'resultado_rename' => $renameResult,
                'resultado_update' => $updateResult
            ]);

        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            LogHelper::logDisk("Erro de parâmetro: " . $e->getMessage(), __CLASS__ . '::' . __FUNCTION__);
            echo json_encode(['erro' => $e->getMessage()]);
        } catch (\Exception $e) {
            http_response_code(500);
            $mensagemErro = $e->getMessage();
            LogHelper::logDisk("Exceção capturada: " . $mensagemErro, __CLASS__ . '::' . __FUNCTION__);
            
            // Adiciona comentário de erro na timeline se tivermos o ID da empresa
            if ($companyid) {
                BitrixHelper::adicionarComentarioTimeline('company', (int)$companyid, "ERRO na automação de renomear pasta: " . $mensagemErro);
            }
            
            echo json_encode(['erro' => $mensagemErro]);
        }
    }
}
