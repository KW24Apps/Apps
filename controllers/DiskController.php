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
        // Remove caracteres especiais, mantendo letras, números, espaços e hífens
        $sanitized = preg_replace('/[^a-zA-Z0-9\s-]/', '', $string);
        // Substitui múltiplos espaços por um único espaço e remove espaços no início/fim
        $sanitized = trim(preg_replace('/\s+/', ' ', $sanitized));
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
            $folderRenamed = true; // Assume renamed initially

            if (isset($renameResult['error'])) {
                $errorDescription = $renameResult['error_description'] ?? 'Erro desconhecido da API.';
                // Check if the error is because the folder already has the target name
                // This is an assumption based on common API behaviors.
                // A more robust solution would require knowing the exact Bitrix24 error code/message.
                if (strpos($errorDescription, 'já possui o nome especificado') !== false || strpos($errorDescription, 'already has the specified name') !== false) {
                    // Folder already has the name, treat as a "soft success" for renaming
                    $folderRenamed = false;
                    // Log this event if necessary, but don't throw an exception
                    LogHelper::logDisk("Pasta ID {$idPastaAlvo} já possui o nome '{$novoNomePasta}'. Nenhuma renomeação foi necessária.", __CLASS__ . '::' . __FUNCTION__);
                } else {
                    // It's a genuine error, re-throw
                    throw new \Exception('Erro ao renomear a pasta: ' . $errorDescription);
                }
            } elseif (empty($renameResult['result'])) {
                // This case means the API call was successful but returned an empty result, which is unexpected for a successful rename.
                // It's safer to treat this as an error unless Bitrix API explicitly states otherwise for "no change" scenarios.
                throw new \Exception('Erro ao renomear a pasta: Resposta inválida da API (resultado vazio).');
            }

            // 7. Atualizar os campos na Company (sem o campo de retorno)
            $companyUpdateData = [
                'id' => $companyid,
                $fieldNomeEmpresaAntigo => $nomePadraoEmpresa,
                $fieldIdDominioAntigo => $idDominioAtual
            ];
            // 8. Obter o link da pasta (seja renomeada ou não) usando o helper
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
            $mensagemSucesso = "";
            if ($folderRenamed) {
                $mensagemSucesso = "Pasta da Contabilidade foi renomeada para: '{$novoNomePasta}'.";
            } else {
                $mensagemSucesso = "Pasta da Contabilidade já possui o nome exato: '{$novoNomePasta}'. Nenhuma renomeação foi necessária.";
            }

            if ($folderLink) {
                // Formata o link usando BBCode para ser clicável na timeline do Bitrix24
                $mensagemSucesso .= "\nLink da Pasta: [url=" . $folderLink . "]Clique Aqui[/url]";
            }
            BitrixHelper::adicionarComentarioTimeline('company', (int)$companyid, $mensagemSucesso);

            // 10. Retornar sucesso
            http_response_code(200);
            echo json_encode([
                'status' => 'sucesso',
                'mensagem' => $mensagemSucesso, // Usa a mensagem dinâmica
                'link_pasta' => $folderLink, // Inclui o link da pasta na resposta da API
                'resultado_rename' => $renameResult, // Inclui o resultado original do rename (pode ter erro se não renomeou)
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
