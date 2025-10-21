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
    /**
     * Remove caracteres especiais de uma string, mantendo apenas letras, números, espaços e hífens.
     *
     * @param string $string A string a ser sanitizada.
     * @return string A string sanitizada.
     */
    private function sanitizeFolderName(string $string): string
    {
        // Remove todos os caracteres que não são letras, números, pontos ou espaços
        $sanitized = preg_replace('/[^a-zA-Z0-9. ]/', '', $string);
        // Não é necessário substituir múltiplos espaços ou remover espaços no início/fim, pois todos os espaços serão removidos pela regex acima.
        return $sanitized;
    }

    public function RenomearPasta()
    {
        header('Content-Type: application/json');
        //sleep(10); // Adiciona um atraso de 5 segundos conforme solicitado pelo usuário

        // IDs dos campos customizados
        $fieldIdDominioAntigo = 'UF_CRM_1756209754';
        $fieldIdDominioAtual = 'UF_CRM_1656592471';
        $fieldNomeEmpresaAntigo = 'UF_CRM_1756209679';
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
            $nomePadraoEmpresa = $companyData['TITLE'] ?? null;

            // A validação foi ajustada para permitir '0' como um valor válido para $busca e $idDominioAtual.
            // '0' não deve ser tratado como vazio, apenas null ou string vazia.
            if (($busca === null || $busca === '') || ($idDominioAtual === null || $idDominioAtual === '') || empty($nomePadraoEmpresa)) {
                throw new \Exception("Campos essenciais (ID Domínio Antigo, ID Domínio Atual, Nome) não encontrados ou são inválidos na empresa ID {$companyid}.");
            }

            // 4. Construir o novo nome da pasta
            // Sanitiza o nome da empresa para remover caracteres especiais antes de construir o nome da pasta
            $nomePadraoEmpresaSanitizado = $this->sanitizeFolderName($nomePadraoEmpresa);
            $novoNomePasta = $idDominioAtual . ' - ' . $nomePadraoEmpresaSanitizado;

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
            // 8. Obter o link da pasta renomeada usando o helper
            $folderLink = BitrixDiskHelper::getFolderDetailUrl($idPastaAlvo);

            // Adiciona o link da pasta ao campo UF_CRM_1660100679 se o link existir
            if ($folderLink) {
                $companyUpdateData['UF_CRM_1660100679'] = $folderLink;
            }

            $updateResult = BitrixCompanyHelper::editarCamposEmpresa($companyUpdateData);
            if (isset($updateResult['error'])) {
                throw new \Exception('Erro ao atualizar a company: ' . ($updateResult['error_description'] ?? 'Erro desconhecido.'));
            }

            // 9. Adicionar comentário de sucesso na timeline
            $mensagemSucesso = "Pasta da Contabilidade foi renomeada para: '{$novoNomePasta}'.";
            if ($folderLink) {
                // Formata o link usando BBCode para ser clicável na timeline do Bitrix24
                $mensagemSucesso .= "\nLink da Pasta: [url=" . $folderLink . "]Clique Aqui[/url]";
            }
            BitrixHelper::adicionarComentarioTimeline('company', (int)$companyid, $mensagemSucesso);

            // 10. Retornar sucesso
            http_response_code(200);
            echo json_encode([
                'status' => 'sucesso',
                'mensagem' => "Pasta ID {$idPastaAlvo} renomeada e comentário adicionado na Company ID {$companyid}.",
                'link_pasta' => $folderLink, // Inclui o link da pasta na resposta da API
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
