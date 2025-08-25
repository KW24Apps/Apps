<?php
namespace Helpers;

require_once __DIR__ . '/../helpers/BitrixHelper.php';

use Helpers\BitrixHelper;

class BitrixDiskHelper
{
    /**
     * Renomeia uma pasta no Bitrix24 Disk.
     *
     * @param int $folderId O ID da pasta a ser renomeada.
     * @param string $newName O novo nome para a pasta.
     * @return array A resposta da API Bitrix24.
     */
    public static function renameFolder($folderId, $newName)
    {
        $response = BitrixHelper::chamarApi(
            'disk.folder.rename',
            [
                'id' => $folderId,
                'newName' => $newName
            ]
        );

        return $response;
    }

    /**
     * Encontra o ID de uma subpasta dentro de uma pasta pai, procurando por um trecho do nome.
     *
     * @param int $parentFolderId O ID da pasta pai onde a busca será feita.
     * @param string $nameSubstring O trecho do nome da subpasta a ser encontrado.
     * @return int|null O ID da primeira subpasta encontrada ou null se nenhuma for encontrada.
     */
    public static function findSubfolderIdByName($parentFolderId, $nameSubstring)
    {
        $children = BitrixHelper::chamarApi('disk.folder.getchildren', ['id' => $parentFolderId]);

        if (isset($children['result']) && is_array($children['result'])) {
            foreach ($children['result'] as $item) {
                // Verifica se é uma pasta e se o nome contém o trecho
                if (isset($item['TYPE']) && $item['TYPE'] === 'folder' && strpos($item['NAME'], $nameSubstring) !== false) {
                    return (int)$item['ID']; // Retorna o ID da primeira pasta encontrada
                }
            }
        }
        
        // Paginação: Se houver mais itens, busca recursivamente (ou em loop)
        if (isset($children['next'])) {
            // Lógica de paginação pode ser adicionada aqui se necessário
            // Por simplicidade, esta versão inicial busca apenas na primeira página de resultados
        }

        return null; // Retorna null se não encontrar
    }
}
